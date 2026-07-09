<?php

namespace App\Http\Controllers\api\v1\customers;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\MyFatoorahService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * GET /v1/myfatoorah/callback?paymentId=xxx
     *
     * MyFatoorah redirects the browser here after a successful web payment.
     * We look up the InvoiceId and deep-link back into the app so it can call
     * pay_booking / orders with the invoice_id to finalise the transaction.
     */
    public function callback(Request $request)
    {
        $paymentId = $request->query('paymentId') ?? $request->query('Id');

        if (! $paymentId) {
            return redirect('fillo://payment/error?reason=missing_payment_id');
        }

        try {
            $myfatoorah = app(MyFatoorahService::class);
            $status = $myfatoorah->getPaymentStatus($paymentId);
            $invoiceId = (string) ($status['Data']['InvoiceId'] ?? $paymentId);
            $invoiceStatus = strtolower($status['Data']['InvoiceStatus'] ?? 'unknown');
        } catch (\Exception $e) {
            Log::error('MyFatoorah callback verification failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return redirect('fillo://payment/error?reason=verification_failed');
        }

        return redirect('fillo://payment/callback?invoice_id='.$invoiceId.'&status='.$invoiceStatus);
    }

    /**
     * GET /v1/myfatoorah/error?paymentId=xxx
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
     * Defense-in-depth: MyFatoorah sends this async notification on payment events.
     * We verify independently and update any pending payment record.
     * The primary confirmation flow is still app-driven (pay_booking / orders).
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();
        Log::info('MyFatoorah webhook received', $payload);

        $invoiceId = (string) ($payload['InvoiceId'] ?? $payload['Data']['InvoiceId'] ?? '');

        if (! $invoiceId) {
            return response()->json(['success' => false, 'message' => 'Missing InvoiceId'], 400);
        }

        try {
            $myfatoorah = app(MyFatoorahService::class);
            $status = $myfatoorah->getPaymentStatus($invoiceId);
            $invoiceStatus = $status['Data']['InvoiceStatus'] ?? '';

            $payment = Payment::where('invoice_id', $invoiceId)->first();

            if ($payment) {
                if ($invoiceStatus === 'Paid' && $payment->status === 'pending') {
                    $payment->update(['status' => 'completed']);
                } elseif (in_array($invoiceStatus, ['Failed', 'Expired']) && $payment->status === 'pending') {
                    $payment->update(['status' => 'failed']);
                }
                Log::info('MyFatoorah webhook: payment updated', [
                    'invoice_id' => $invoiceId,
                    'invoice_status' => $invoiceStatus,
                    'payment_status' => $payment->fresh()->status,
                ]);
            } else {
                Log::info('MyFatoorah webhook: no local payment record yet', [
                    'invoice_id' => $invoiceId,
                    'invoice_status' => $invoiceStatus,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('MyFatoorah webhook error', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Webhook processing failed'], 500);
        }

        return response()->json(['success' => true], 200);
    }
}
