<?php

namespace App\services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MyFatoorahService
{
    protected string $baseUrl;

    protected string $apiKey;

    public function __construct()
    {
        $testMode = config('services.myfatoorah.test_mode', true);
        $this->baseUrl = $testMode
            ? 'https://apitest.myfatoorah.com'
            : 'https://api.myfatoorah.com';
        $this->apiKey = $testMode
            ? config('services.myfatoorah.test_api_key', '')
            : config('services.myfatoorah.live_api_key', '');
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Fetch raw payment status from MyFatoorah by InvoiceId.
     * Throws on HTTP failure or when IsSuccess = false.
     */
    public function getPaymentStatus(string $invoiceId): array
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/v2/GetPaymentStatus", [
                'Key' => $invoiceId,
                'KeyType' => 'InvoiceId',
            ]);

        if (! $response->successful()) {
            Log::error('MyFatoorah GetPaymentStatus HTTP error', [
                'invoice_id' => $invoiceId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception(__('responses.Payment verification service unavailable'));
        }

        $body = $response->json();

        if (! ($body['IsSuccess'] ?? false)) {
            Log::warning('MyFatoorah GetPaymentStatus returned IsSuccess=false', [
                'invoice_id' => $invoiceId,
                'message' => $body['Message'] ?? '',
            ]);
            throw new \Exception($body['Message'] ?? __('responses.Payment verification failed'));
        }

        return $body;
    }

    /**
     * Verify a payment against the expected SAR amount.
     * Returns structured payment data on success; throws \Exception on any failure.
     *
     * @throws \Exception
     */
    public function verifyPayment(string $invoiceId, float $expectedAmount): array
    {
        $body = $this->getPaymentStatus($invoiceId);
        $data = $body['Data'] ?? [];

        $invoiceStatus = $data['InvoiceStatus'] ?? '';
        if ($invoiceStatus !== 'Paid') {
            throw new \Exception(__('responses.Payment is not completed'));
        }

        $invoiceValue = (float) ($data['InvoiceValue'] ?? 0);
        if (abs($invoiceValue - $expectedAmount) > 0.01) {
            Log::warning('MyFatoorah amount mismatch', [
                'invoice_id' => $invoiceId,
                'expected' => $expectedAmount,
                'received' => $invoiceValue,
            ]);
            throw new \Exception(__('responses.Payment amount mismatch'));
        }

        // Locate the successful transaction (MyFatoorah typos "Succss" in some versions)
        $transactions = collect($data['InvoiceTransactions'] ?? []);
        $successTx = $transactions->first(
            fn ($t) => in_array($t['TransactionStatus'] ?? '', ['Succss', 'Success', 'Successful'])
        );

        return [
            'invoice_id' => (string) ($data['InvoiceId'] ?? $invoiceId),
            'invoice_status' => $invoiceStatus,
            'invoice_value' => $invoiceValue,
            'payment_id' => $successTx['PaymentId'] ?? null,
            'transaction_id' => $successTx['TransactionId'] ?? null,
            'payment_gateway' => $successTx['PaymentGateway'] ?? null,
        ];
    }

    /**
     * Retrieve available payment methods for a given amount (SDK InitiatePayment step).
     */
    public function initiatePayment(float $amount, string $currency = 'SAR'): array
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/v2/InitiatePayment", [
                'InvoiceAmount' => $amount,
                'CurrencyIso' => $currency,
            ]);

        if (! $response->successful()) {
            throw new \Exception('MyFatoorah InitiatePayment failed: '.$response->body());
        }

        $body = $response->json();
        if (! ($body['IsSuccess'] ?? false)) {
            throw new \Exception($body['Message'] ?? 'InitiatePayment failed');
        }

        return $body;
    }

    /**
     * Create a hosted payment page (web/redirect flow).
     *
     * @param  array  $payload  See MyFatoorah ExecutePayment docs for full schema.
     *                          Required: InvoiceValue, CustomerName, CustomerMobile,
     *                          CustomerEmail, CallBackUrl, ErrorUrl.
     *                          Pass PaymentMethodId = null to show all methods.
     */
    public function executePayment(array $payload): array
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/v2/ExecutePayment", $payload);

        if (! $response->successful()) {
            Log::error('MyFatoorah ExecutePayment HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to create payment URL: '.$response->body());
        }

        $body = $response->json();
        if (! ($body['IsSuccess'] ?? false)) {
            Log::error('MyFatoorah ExecutePayment failed', ['message' => $body['Message'] ?? '']);
            throw new \Exception($body['Message'] ?? 'ExecutePayment failed');
        }

        return $body;
    }

    /**
     * Issue a refund via MyFatoorah MakeRefund API.
     *
     * @param  string  $paymentId  The PaymentId from the original transaction.
     * @param  float  $amount  Amount in SAR to refund.
     * @param  string  $comment  Reason shown in MyFatoorah dashboard.
     *
     * @throws \Exception on failure (caller should decide whether to rollback)
     */
    public function makeRefund(string $paymentId, float $amount, string $comment = 'Refund'): array
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/v2/MakeRefund", [
                'Key' => $paymentId,
                'KeyType' => 'PaymentId',
                'RefundChargeOnCustomer' => false,
                'ServiceChargeOnCustomer' => false,
                'Amount' => $amount,
                'Comment' => $comment,
                'AmountDeductedFromSupplier' => 0,
            ]);

        if (! $response->successful()) {
            Log::error('MyFatoorah MakeRefund HTTP error', [
                'payment_id' => $paymentId,
                'amount' => $amount,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Refund request failed: '.$response->body());
        }

        $body = $response->json();
        if (! ($body['IsSuccess'] ?? false)) {
            Log::error('MyFatoorah MakeRefund returned IsSuccess=false', [
                'payment_id' => $paymentId,
                'message' => $body['Message'] ?? '',
            ]);
            throw new \Exception($body['Message'] ?? 'Refund failed');
        }

        return $body;
    }

    /**
     * Extract InvoiceId from the raw payment_response JSON string sent by the app.
     * Returns null when not found so callers can fall back to a request field.
     */
    public static function extractInvoiceIdFromResponse(?string $paymentResponseJson): ?string
    {
        if (! $paymentResponseJson) {
            return null;
        }

        $decoded = json_decode($paymentResponseJson, true);
        if (! is_array($decoded)) {
            return null;
        }

        $id = $decoded['Data']['InvoiceId']
            ?? $decoded['InvoiceId']
            ?? $decoded['Data']['invoiceId']
            ?? null;

        return $id !== null ? (string) $id : null;
    }
}
