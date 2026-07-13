<?php

namespace App\Http\Controllers\api\v1\customers;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Notifications\customers\OrderCancelledNotification;
use App\services\MyFatoorahService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * GET /v1/myfatoorah/callback?paymentId=xxx
     *
     * MyFatoorah redirects the browser here after a web payment completes.
     * We deep-link back into the app so it can refresh the order/booking screen.
     * The actual DB update is handled by the webhook, not here.
     */
    public function callback(Request $request)
    {
        $paymentId = $request->query('paymentId') ?? $request->query('Id');

        if (! $paymentId) {
            return redirect('fillo://payment/error?reason=missing_payment_id');
        }

        try {
            // MF's CallBackUrl query param is a PaymentId (transaction id), not an InvoiceId.
            $status = app(MyFatoorahService::class)->getPaymentStatus($paymentId, 'PaymentId');
            $invoiceId = (string) ($status['Data']['InvoiceId'] ?? $paymentId);
            $invoiceStatus = strtolower($status['Data']['InvoiceStatus'] ?? 'unknown');
        } catch (\Exception $e) {
            Log::error('MyFatoorah callback failed', ['payment_id' => $paymentId, 'error' => $e->getMessage()]);

            return redirect('fillo://payment/error?reason=verification_failed');
        }

        return redirect('fillo://payment/callback?invoice_id='.$invoiceId.'&status='.$invoiceStatus);
    }

    /**
     * GET /v1/myfatoorah/error
     *
     * MyFatoorah redirects here on payment failure or user cancellation.
     */
    public function error(Request $request)
    {
        $paymentId = $request->query('paymentId') ?? $request->query('Id', '');

        return redirect('fillo://payment/error?payment_id='.urlencode($paymentId));
    }

    /**
     * POST /v1/myfatoorah/webhook
     *
     * The single source of truth for payment confirmation. Nothing
     * irreversible (stock decrement, cart clearing) happens anywhere else in
     * the app — it all happens here, only once payment is verified.
     *
     * On PAID:
     *   - Verify InvoiceValue matches our server-calculated Payment amount
     *     (defense in depth — critical for the SDK flow, where the invoice
     *     amount was set by the app on-device, not by us).
     *   - Re-check stock (it was never reserved at checkout) and decrement it.
     *     If stock ran out in the meantime, auto-refund + cancel instead.
     *   - Clear the matching cart rows, mark payment completed, notify.
     *
     * On FAILED / EXPIRED / amount mismatch:
     *   - Mark payment failed, cancel the order/booking. Stock/cart were
     *     never touched, so there's nothing to roll back.
     *
     * Idempotent: if payment already processed, skip silently and return 200.
     *
     * SDK-flow matching: for payments created with payment_source=sdk, our
     * Payment row has invoice_id=null because the invoice is created by the
     * app on-device via the native SDK, not by us. We locate it via the
     * order_id/booking_id encoded in UserDefinedField, which the app must
     * attach to its SDK call, and set invoice_id here on first sight.
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();
        Log::info('MyFatoorah webhook received', ['payload' => $payload]);

        // MyFatoorah sends several event types on this same URL. Code 1 is
        // PAYMENT_STATUS_CHANGED (handled below). Code 2 is
        // REFUND_STATUS_CHANGED, which has a completely different payload
        // shape (Data.Refund / Data.ReferencedInvoice, no Data.Invoice.Id) —
        // it must be routed separately rather than falling into the
        // InvoiceId extraction below, which would always fail for it.
        $eventCode = $payload['Event']['Code'] ?? null;
        if ($eventCode === 2) {
            return $this->handleRefundStatusChanged($payload);
        }

        // Webhook V2 nests it at Data.Invoice.Id. Older/flat shapes are kept
        // as fallbacks in case the account is on Webhook V1 or a different event.
        $invoiceId = (string) (
            $payload['Data']['Invoice']['Id']
            ?? $payload['InvoiceId']
            ?? $payload['Data']['InvoiceId']
            ?? ''
        );

        if (! $invoiceId) {
            Log::warning('MyFatoorah webhook: could not extract InvoiceId', ['payload' => $payload]);

            return response()->json(['success' => false, 'message' => 'Missing InvoiceId'], 400);
        }

        // Always verify server-side — never trust the webhook payload alone
        try {
            $mfStatus = app(MyFatoorahService::class)->getPaymentStatus($invoiceId);
        } catch (\Exception $e) {
            Log::error('MyFatoorah webhook: GetPaymentStatus failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            // Return 500 so MF will retry
            return response()->json(['success' => false, 'message' => 'Verification failed'], 500);
        }

        $invoiceData = $mfStatus['Data'] ?? [];
        $invoiceStatus = $invoiceData['InvoiceStatus'] ?? '';
        // "InvoiceValue" is in the account's BASE currency (e.g. KWD on a
        // Kuwait-provisioned test account), not the SAR we display to
        // customers. "InvoiceDisplayValue" is the SAR-denominated amount —
        // that's what must match our server-calculated Payment amount.
        $invoiceValue = (float) ($invoiceData['InvoiceDisplayValue'] ?? $invoiceData['InvoiceValue'] ?? 0);

        // Extract transaction details from the successful transaction row
        $transactions = collect($invoiceData['InvoiceTransactions'] ?? []);
        $successTx = $transactions->first(
            fn ($t) => in_array($t['TransactionStatus'] ?? '', ['Succss', 'Success', 'Successful'])
        );

        DB::beginTransaction();

        try {
            $payment = Payment::where('invoice_id', $invoiceId)
                ->lockForUpdate()
                ->first();

            // SDK flow fallback: match by the reference the app attached to
            // its on-device SDK call, then remember this invoice_id.
            //
            // IMPORTANT: do NOT require invoice_id IS NULL here. Each SDK
            // payment *attempt* (e.g. retry after a declined card) gets its
            // own brand-new MyFatoorah invoice, so a Payment's invoice_id
            // gets overwritten on every attempt. Requiring it to still be
            // null would only ever match the very first attempt — any later
            // attempt's webhook (including the one that actually succeeds)
            // would find nothing and silently be dropped. Payment::booking_id
            // /order_id are unique (enforced at the DB level), so matching
            // on {type}_id + status=pending alone is safe and always correct.
            if (! $payment) {
                $udf = MyFatoorahService::parseUserDefinedField($invoiceData['UserDefinedField'] ?? null);

                if ($udf) {
                    $payment = Payment::where($udf['type'].'_id', $udf['id'])
                        ->where('payment_source', 'sdk')
                        ->where('status', 'pending')
                        ->lockForUpdate()
                        ->first();

                    if ($payment && $payment->invoice_id !== $invoiceId) {
                        $payment->invoice_id = $invoiceId;
                        $payment->save();
                    }
                }
            }

            if (! $payment) {
                DB::commit();
                Log::warning('MyFatoorah webhook: no payment record found', ['invoice_id' => $invoiceId]);

                return response()->json(['success' => true], 200);
            }

            if ($payment->status !== 'pending') {
                DB::commit();
                Log::info('MyFatoorah webhook: already processed', [
                    'invoice_id' => $invoiceId,
                    'status' => $payment->status,
                ]);

                return response()->json(['success' => true], 200);
            }

            // ── PAID ──────────────────────────────────────────────────────────
            if ($invoiceStatus === 'Paid') {
                if (abs($invoiceValue - (float) $payment->amount) > 0.01) {
                    Log::error('MyFatoorah webhook: amount mismatch — refusing to confirm', [
                        'invoice_id' => $invoiceId,
                        'expected' => $payment->amount,
                        'received' => $invoiceValue,
                    ]);

                    $payment->update(['status' => 'failed']);
                    $this->cancelRelatedOrderOrBooking($payment, 'Payment amount mismatch detected');
                    DB::commit();

                    return response()->json(['success' => true], 200);
                }

                $payment->update([
                    'status' => 'completed',
                    'payment_method' => $successTx['PaymentGateway'] ?? null,
                    'transaction_id' => $successTx['TransactionId'] ?? null,
                    // MakeRefund needs THIS (PaymentId), not TransactionId above.
                    'mf_payment_id' => $successTx['PaymentId'] ?? null,
                    // Base-currency (account currency) value — the amount
                    // MakeRefund actually validates against, not the SAR
                    // amount in `amount`/InvoiceDisplayValue.
                    'mf_base_amount' => $invoiceData['InvoiceValue'] ?? null,
                ]);

                if ($payment->order_id && $payment->order) {
                    $order = $payment->order;

                    // Stock was never reserved at checkout — verify + decrement now.
                    $stockShort = false;
                    foreach ($order->items as $item) {
                        $variant = ProductVariant::where('id', $item->product_variant_id)
                            ->lockForUpdate()
                            ->first();

                        if (! $variant || $variant->quantity < $item->quantity) {
                            $stockShort = true;
                            break;
                        }
                    }

                    if ($stockShort) {
                        Log::error('MyFatoorah webhook: stock ran out after payment — refunding', [
                            'order_id' => $order->id,
                            'invoice_id' => $invoiceId,
                        ]);

                        $this->refundAndCancelOrder($payment, $order);
                        DB::commit();

                        return response()->json(['success' => true], 200);
                    }

                    foreach ($order->items as $item) {
                        ProductVariant::where('id', $item->product_variant_id)
                            ->decrement('quantity', $item->quantity);
                    }

                    Cart::where('customer_id', $order->customer_id)
                        ->whereIn('product_variant_id', $order->items->pluck('product_variant_id'))
                        ->delete();

                    dispatch(new SendNotificationJob(
                        collect([$order->customer]),
                        null,
                        'responses.Payment Received',
                        'responses.Your order payment was received and is under review',
                        true,
                        [],
                        ['type' => 'order_payment_received', 'order_id' => $order->id]
                    ));

                    Log::info('MyFatoorah webhook: order payment confirmed, stock reserved', [
                        'order_id' => $order->id,
                        'invoice_id' => $invoiceId,
                    ]);
                }

                if ($payment->booking_id && $payment->booking) {
                    $booking = $payment->booking;

                    dispatch(new SendNotificationJob(
                        collect([$booking->customer]),
                        null,
                        'responses.Payment Received',
                        'responses.Your booking payment was received and is under review',
                        true,
                        [],
                        ['type' => 'booking_payment_received', 'booking_id' => $booking->id]
                    ));

                    Log::info('MyFatoorah webhook: booking payment confirmed', [
                        'booking_id' => $booking->id,
                        'invoice_id' => $invoiceId,
                    ]);
                }
            }

            // ── FAILED / EXPIRED / NOT PAID ─────────────────────────────────────
            elseif (in_array($invoiceStatus, ['Failed', 'Expired', 'NotPaid'])) {
                $payment->update(['status' => 'failed']);
                $this->cancelRelatedOrderOrBooking($payment, 'Payment failed via MyFatoorah ('.$invoiceStatus.')');
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MyFatoorah webhook: DB error', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Internal error'], 500);
        }

        return response()->json(['success' => true], 200);
    }

    /**
     * Handle MyFatoorah's REFUND_STATUS_CHANGED (Event.Code=2) webhook.
     *
     * Refunds we issue ourselves (admin panel / auto-refund on stock-out)
     * already mark the Payment 'refunded' synchronously from the MakeRefund
     * response — this webhook is best-effort reconciliation for refunds
     * issued directly from the MyFatoorah merchant portal, bypassing our
     * app entirely, so our DB doesn't silently drift out of sync.
     *
     * Always returns 200 (nothing here is worth MyFatoorah retrying).
     */
    private function handleRefundStatusChanged(array $payload): \Illuminate\Http\JsonResponse
    {
        $data = $payload['Data'] ?? [];
        $status = $data['Refund']['Status'] ?? null;
        // NOTE: Data.Refund.InvoiceId is NOT the original invoice — it's a
        // different reference tied to the refund itself. The invoice that
        // matches our stored Payment.invoice_id is Data.ReferencedInvoice.Id.
        $invoiceId = (string) ($data['ReferencedInvoice']['Id'] ?? '');

        if ($status !== 'REFUNDED' || ! $invoiceId) {
            Log::info('MyFatoorah webhook: refund event ignored', ['status' => $status, 'invoice_id' => $invoiceId]);

            return response()->json(['success' => true], 200);
        }

        DB::beginTransaction();
        try {
            $payment = Payment::where('invoice_id', $invoiceId)->lockForUpdate()->first();

            if (! $payment) {
                DB::commit();
                Log::warning('MyFatoorah webhook: refund event — no matching payment found', ['invoice_id' => $invoiceId]);

                return response()->json(['success' => true], 200);
            }

            if ($payment->status !== 'refunded') {
                $payment->update([
                    'status' => 'refunded',
                    'refunded_amount' => $payment->refunded_amount > 0 ? $payment->refunded_amount : $payment->amount,
                ]);

                Log::info('MyFatoorah webhook: payment reconciled to refunded (likely refunded via MyFatoorah portal directly)', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoiceId,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MyFatoorah webhook: refund event DB error', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Internal error'], 500);
        }

        return response()->json(['success' => true], 200);
    }

    /**
     * Cancel the pending order/booking tied to a failed/rejected payment.
     * Stock and cart are never touched before payment confirmation, so
     * there's nothing to restore here — just mark things cancelled and notify.
     *
     * $detail is a free-text note for admins; `cancellation_reason` itself is
     * a strict DB enum (administrative|customer_not_received), so all
     * system-initiated cancellations use 'administrative' and the specific
     * reason goes into admin_notes instead.
     */
    private function cancelRelatedOrderOrBooking(Payment $payment, string $detail): void
    {
        if ($payment->order_id && $payment->order) {
            $order = $payment->order;

            $order->update([
                'order_status' => 'cancelled',
                'cancellation_reason' => 'administrative',
                'admin_notes' => $detail,
            ]);

            dispatch(new SendNotificationJob(
                collect([$order->customer]),
                new OrderCancelledNotification($order, 0),
                'responses.Payment Failed',
                'responses.Your order was cancelled due to payment failure',
                true,
                [],
                ['type' => 'order_payment_failed', 'order_id' => $order->id]
            ));

            Log::info('MyFatoorah webhook: order cancelled', ['order_id' => $order->id, 'reason' => $detail]);
        }

        if ($payment->booking_id && $payment->booking) {
            $booking = $payment->booking;
            $booking->update(['order_status' => 'cancelled']);

            dispatch(new SendNotificationJob(
                collect([$booking->customer]),
                null,
                'responses.Payment Failed',
                'responses.Your booking was cancelled due to payment failure',
                true,
                [],
                ['type' => 'booking_payment_failed', 'booking_id' => $booking->id]
            ));

            Log::info('MyFatoorah webhook: booking cancelled', ['booking_id' => $booking->id, 'reason' => $detail]);
        }
    }

    /**
     * Rare race: the customer paid successfully but stock ran out in the
     * window between checkout and payment confirmation (nothing was reserved
     * up front, by design). Refund automatically and cancel the order.
     */
    private function refundAndCancelOrder(Payment $payment, Order $order): void
    {
        if ($payment->mf_payment_id) {
            try {
                app(MyFatoorahService::class)->refundPayment($payment, 'Out of stock - automatic refund');

                $payment->update(['status' => 'refunded', 'refunded_amount' => $payment->amount]);
            } catch (\Exception $e) {
                Log::error('MyFatoorah webhook: automatic refund failed — needs manual admin refund', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->mf_payment_id,
                    'error' => $e->getMessage(),
                ]);

                // Keep as completed (money was collected) so admins see it and refund manually.
                $payment->update(['status' => 'completed']);
            }
        } else {
            Log::error('MyFatoorah webhook: no mf_payment_id to refund — needs manual admin refund', [
                'order_id' => $order->id,
            ]);
            $payment->update(['status' => 'completed']);
        }

        $order->update([
            'order_status' => 'cancelled',
            'cancellation_reason' => 'administrative',
            'admin_notes' => 'Stock ran out after payment was collected — refund issued automatically',
        ]);

        dispatch(new SendNotificationJob(
            collect([$order->customer]),
            new OrderCancelledNotification($order, 0),
            'responses.Order Cancelled - Refund Issued',
            'responses.Your order was cancelled because an item went out of stock. A refund has been issued.',
            true,
            [],
            ['type' => 'order_out_of_stock_refund', 'order_id' => $order->id]
        ));
    }
}
