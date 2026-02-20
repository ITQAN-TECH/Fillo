<?php

namespace App\services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CurrencyService
{
    /**
     * Get the exchange rate between two currencies.
     *
     * @param  string  $toCurrency
     */
    public function getRate(string $fromCurrency): float
    {
        $toCurrency = session('current_currency');
        if (Auth::guard('customers')->check()) {
            $customer = Auth::guard('customers')->user();
            $toCurrency = $customer->currency;
        }
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }
        // Use cache to store the exchange rate for 24 hours
        $cacheKey = "exchange_rate_{$fromCurrency}_{$toCurrency}";

        return Cache::remember($cacheKey, now()->addDay(), function () use ($fromCurrency, $toCurrency) {
            $apiKey = config('services.exchange_rate.api_key');
            if (! $apiKey) {
                if (Auth::guard('customers')->check()) {
                    $customer = Auth::guard('customers')->user();
                    $customer->update(['currency' => 'SAR']);
                }

                return 1; // A safe default for SAR to USD
            }

            try {
                // Fetch the latest exchange rates from the API
                $response = Http::get("https://v6.exchangerate-api.com/v6/{$apiKey}/latest/{$fromCurrency}");

                if ($response->successful() && isset($response->json()['conversion_rates'])) {
                    $data = $response->json();

                    return $data['conversion_rates'][$toCurrency] ?? 1;
                }
                if (Auth::guard('customers')->check()) {
                    $customer = Auth::guard('customers')->user();
                    $customer->update(['currency' => 'SAR']);
                }

                return 1;

            } catch (\Exception $e) {
                if (Auth::guard('customers')->check()) {
                    $customer = Auth::guard('customers')->user();
                    $customer->update(['currency' => 'SAR']);
                }

                return 1;
            }
        });
    }
}
