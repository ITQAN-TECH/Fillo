<?php

namespace App\Http\Controllers\api\v1\customers;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
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
            $status = app(MyFatoorahService::class)->getPaymentStatus($paymentId);
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
     * The single source of truth for payment confirmation.
     * MyFatoorah calls this endpoint for every payment event (paid, failed, expired).
     *
     * On PAID:
     *   - Update Payment → completed (payment_method, transaction_id from MF).
     *   - Order/Booking stays 'pending' — admin still needs to confirm.
     *   - Notify customer that payment was received.
     *
     * On FAILED / EXPIRED:
     *   - Update Payment → failed.
     *   - Cancel the Order/Booking.
     *   - Restore inventory for orders.
     *   - Notify customer.
     *
     * Idempotent: if payment already processed, skip silently and return 200.
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();
        Log::info('MyFatoorah webhook received', ['payload' => $payload]);

        $invoiceId = (string) ($payload['InvoiceId'] ?? $payload['Data']['InvoiceId'] ?? '');

        if (! $invoiceId) {
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

        $invoiceStatus = $mfStatus['Data']['InvoiceStatus'] ?? '';
        $invoiceData = $mfStatus['Data'] ?? [];

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
                $payment->update([
                    'status' => 'completed',
                    'payment_method' => $successTx['PaymentGateway'] ?? null,
                    'transaction_id' => $successTx['TransactionId'] ?? null,
                ]);

                // Notify customer — payment received, waiting for admin confirmation
                if ($payment->order_id && $payment->order) {
                    $order = $payment->order;
                    dispatch(new SendNotificationJob(
                        collect([$order->customer]),
                        null,
                        'responses.Payment Received',
                        'responses.Your order payment was received and is under review',
                        true,
                        [],
                        ['type' => 'order_payment_received', 'order_id' => $order->id]
                    ));

                    Log::info('MyFatoorah webhook: order payment confirmed', [
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

            // ── FAILED / EXPIRED ──────────────────────────────────────────────
            elseif (in_array($invoiceStatus, ['Failed', 'Expired', 'NotPaid'])) {
                $payment->update(['status' => 'failed']);

                if ($payment->order_id && $payment->order) {
                    $order = $payment->order;

                    // Restore inventory
                    foreach ($order->items as $item) {
                        ProductVariant::where('id', $item->product_variant_id)
                            ->increment('quantity', $item->quantity);
                    }

                    $order->update([
                        'order_status' => 'cancelled',
                        'cancellation_reason' => 'administrative',
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

                    Log::info('MyFatoorah webhook: order cancelled (payment failed)', [
                        'order_id' => $order->id,
                        'invoice_id' => $invoiceId,
                    ]);
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

                    Log::info('MyFatoorah webhook: booking cancelled (payment failed)', [
                        'booking_id' => $booking->id,
                        'invoice_id' => $invoiceId,
                    ]);
                }
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
}
