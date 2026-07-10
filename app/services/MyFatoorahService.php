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
     * Fetch raw payment status from MyFatoorah by InvoiceId (default) or PaymentId.
     * The CallBackUrl/ErrorUrl query param MF redirects with is a PaymentId, NOT
     * an InvoiceId — callers must pass $keyType = 'PaymentId' in that case.
     * Throws on HTTP failure or when IsSuccess = false.
     */
    public function getPaymentStatus(string $key, string $keyType = 'InvoiceId'): array
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/v2/GetPaymentStatus", [
                'Key' => $key,
                'KeyType' => $keyType,
            ]);

        if (! $response->successful()) {
            Log::error('MyFatoorah GetPaymentStatus HTTP error', [
                'key' => $key,
                'key_type' => $keyType,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception(__('responses.Payment verification service unavailable'));
        }

        $body = $response->json();

        if (! ($body['IsSuccess'] ?? false)) {
            Log::warning('MyFatoorah GetPaymentStatus returned IsSuccess=false', [
                'key' => $key,
                'key_type' => $keyType,
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

        // IMPORTANT: "InvoiceValue" is in the MERCHANT ACCOUNT's base currency
        // (e.g. KWD for a Kuwait-provisioned account), NOT the currency we
        // asked to display (DisplayCurrencyIso=SAR everywhere in this app).
        // "InvoiceDisplayValue" is the one actually denominated in SAR.
        $invoiceValue = (float) ($data['InvoiceDisplayValue'] ?? $data['InvoiceValue'] ?? 0);
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
     * Create a hosted "Invoice Link" page (web/browser flow) that shows ALL
     * payment methods enabled on the account and lets the customer pick one —
     * unlike ExecutePayment, no PaymentMethodId is required or forced.
     *
     * @param  array  $payload  Required: InvoiceValue, CustomerName.
     *                          Common optional: CustomerMobile, CustomerEmail,
     *                          CallBackUrl, ErrorUrl, InvoiceItems, etc.
     */
    public function sendPayment(array $payload): array
    {
        $payload = array_filter($payload, fn ($v) => $v !== null);
        $payload['NotificationOption'] = $payload['NotificationOption'] ?? 'LNK';

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/v2/SendPayment", $payload);

        if (! $response->successful()) {
            Log::error('MyFatoorah SendPayment HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to create payment URL: '.$response->body());
        }

        $body = $response->json();
        if (! ($body['IsSuccess'] ?? false)) {
            Log::error('MyFatoorah SendPayment failed', [
                'message' => $body['Message'] ?? '',
                'errors' => $body['ValidationErrors'] ?? [],
            ]);
            throw new \Exception($body['Message'] ?? 'SendPayment failed');
        }

        return $body;
    }

    /**
     * Create a payment against a SPECIFIC payment method (direct charge).
     * Only used when a PaymentMethodId (or SessionId for embedded card) is
     * already known — e.g. an admin-initiated manual charge. NOT used for the
     * customer-facing web flow (see sendPayment) since it forces one method.
     *
     * @param  array  $payload  Required: InvoiceValue, PaymentMethodId (or SessionId).
     */
    public function executePayment(array $payload): array
    {
        $payload = array_filter($payload, fn ($v) => $v !== null);

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
            Log::error('MyFatoorah ExecutePayment failed', [
                'message' => $body['Message'] ?? '',
                'errors' => $body['ValidationErrors'] ?? [],
            ]);
            throw new \Exception($body['Message'] ?? 'ExecutePayment failed');
        }

        return $body;
    }

    /**
     * Split a raw phone number into MyFatoorah's expected MobileCountryCode +
     * CustomerMobile fields. Works for any country's numbers, since customers
     * may register with different country codes (e.g. 966500000000, 963937762825).
     *
     * @return array{country_code: string, mobile: string}
     */
    public static function splitPhone(?string $phone): array
    {
        if (! $phone) {
            return ['country_code' => '', 'mobile' => ''];
        }

        $raw = preg_replace('/[^0-9+]/', '', $phone);
        if (! str_starts_with($raw, '+')) {
            $raw = '+'.$raw;
        }

        $util = \libphonenumber\PhoneNumberUtil::getInstance();

        try {
            // Assumes the number already includes its country code (e.g. 966500000000)
            $parsed = $util->parse($raw, null);

            return [
                'country_code' => (string) $parsed->getCountryCode(),
                'mobile' => substr((string) $parsed->getNationalNumber(), 0, 11),
            ];
        } catch (\Exception $e) {
            // Fallback: no country code present, assume Saudi Arabia (app default)
            try {
                $parsed = $util->parse(ltrim($raw, '+'), 'SA');

                return [
                    'country_code' => (string) $parsed->getCountryCode(),
                    'mobile' => substr((string) $parsed->getNationalNumber(), 0, 11),
                ];
            } catch (\Exception $e2) {
                Log::warning('Failed to parse phone number for MyFatoorah', ['phone' => $phone]);

                return ['country_code' => '', 'mobile' => substr(ltrim($raw, '+'), 0, 11)];
            }
        }
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
     * Parse "order_id:5" / "booking_id:12" back into ['type' => 'order', 'id' => 5].
     * Used by the webhook to locate the pending Payment for SDK-flow invoices,
     * whose invoice_id is unknown to us until MyFatoorah reports it back.
     */
    public static function parseUserDefinedField(?string $userDefinedField): ?array
    {
        if ($userDefinedField && preg_match('/^(order|booking)_id:(\d+)$/', $userDefinedField, $m)) {
            return ['type' => $m[1], 'id' => (int) $m[2]];
        }

        return null;
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
