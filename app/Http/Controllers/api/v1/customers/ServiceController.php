<?php

namespace App\Http\Controllers\api\v1\customers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\Payment;
use App\Models\Rate;
use App\Models\Service;
use App\services\MyFatoorahService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServiceController extends Controller
{
    public function calculatePrice(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'coupon_code' => 'nullable|string|exists:coupons,code',
        ]);
        $service = Service::findOrFail($request->service_id);

        if (! $service->status) {
            return response()->json([
                'success' => false,
                'message' => __('responses.Service is not available'),
            ], 400);
        }

        $serviceProviderPrice = $service->service_provider_price;
        $salePrice = $service->sale_price;
        $profitAmount = $service->profit_amount;

        $discountPercentage = 0;
        $discountAmount = 0;
        $couponId = null;
        $couponCode = null;

        if ($request->coupon_code) {
            $coupon = Coupon::where('code', $request->coupon_code)
                ->where('status', true)
                ->whereIn('type', ['service', 'both_products_and_services'])
                ->where('expiry_date', '>', now())
                ->first();

            if (! $coupon) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.Invalid or expired coupon code'),
                ], 400);
            }

            $couponId = $coupon->id;
            $couponCode = $coupon->code;
            $discountPercentage = $coupon->discount_percentage;
            $discountAmount = ($salePrice * $discountPercentage) / 100;
        }

        $serviceProviderPriceAfterDiscount = $serviceProviderPrice - (($serviceProviderPrice * $discountPercentage) / 100);
        $salePriceAfterDiscount = $salePrice - $discountAmount;
        $profitAmountAfterDiscount = $salePriceAfterDiscount - $serviceProviderPriceAfterDiscount;

        return response()->json([
            'success' => true,
            'message' => __('responses.Price calculated successfully'),
            'data' => [
                'price_before_discount' => $salePrice,
                'discount_amount' => $discountAmount,
                'price_after_discount' => $salePriceAfterDiscount,
            ],
        ], 200);
    }

    public function payBooking(Request $request)
    {
        /**
         * Create a booking and kick off a MyFatoorah payment.
         *
         * Flow:
         *   1. Validate service, address, city coverage, date/time, coupon.
         *   2. Calculate final amount server-side.
         *   3. Create Booking as 'pending' inside a transaction.
         *   4. payment_source=web → create a MyFatoorah "SendPayment" invoice
         *      (shows ALL enabled payment methods) and return payment_url.
         *      payment_source=sdk → no backend MyFatoorah call; the native
         *      SDK talks to MyFatoorah directly on-device. We return the
         *      amount + customer fields + the reference fields the app MUST
         *      attach to its SDK call, so the webhook can match it back.
         *   5. Create a pending Payment record (invoice_id set now for web,
         *      or left null for sdk — the webhook fills it in on first callback).
         *
         * The webhook (POST /v1/myfatoorah/webhook) is the single source of truth:
         *   paid    → mark payment completed (booking stays pending for admin).
         *   failed  → cancel booking, mark payment failed.
         */
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'customer_address_id' => 'required|exists:customer_addresses,id',
            'order_date' => 'required|date|after_or_equal:today',
            'order_time' => 'required|date_format:H:i',
            'coupon_code' => 'nullable|string|exists:coupons,code',
            'payment_source' => 'required|in:web,sdk',
        ]);

        $customer = Auth::guard('customers')->user();

        $address = CustomerAddress::where('id', $request->customer_address_id)
            ->where('customer_id', $customer->id)
            ->first();

        if (! $address) {
            return response()->json([
                'success' => false,
                'message' => __('responses.Address not found or does not belong to you'),
            ], 404);
        }

        $service = Service::with('serviceProvider.citiesOfWorking')->find($request->service_id);

        if (! $service->status) {
            return response()->json([
                'success' => false,
                'message' => __('responses.Service is not available'),
            ], 400);
        }

        $serviceProviderCityIds = $service->serviceProvider->citiesOfWorking->pluck('id')->toArray();

        if (! in_array($address->city_id, $serviceProviderCityIds)) {
            return response()->json([
                'success' => false,
                'message' => __('responses.This service is not available in your area'),
            ], 400);
        }

        // ── Server-side amount calculation ────────────────────────────────────
        $serviceProviderPrice = $service->service_provider_price;
        $salePrice = $service->sale_price;
        $profitAmount = $service->profit_amount;
        $discountPercentage = 0;
        $discountAmount = 0;
        $couponId = null;
        $couponCode = null;

        if ($request->coupon_code) {
            $coupon = Coupon::where('code', $request->coupon_code)
                ->where('status', true)
                ->where('expiry_date', '>', now())
                ->whereIn('type', ['service', 'both_products_and_services'])
                ->first();

            if (! $coupon) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.Invalid or expired coupon code'),
                ], 400);
            }

            $couponId = $coupon->id;
            $couponCode = $coupon->code;
            $discountPercentage = $coupon->discount_percentage;
            $discountAmount = ($salePrice * $discountPercentage) / 100;
        }

        $serviceProviderPriceAfterDiscount = $serviceProviderPrice - (($serviceProviderPrice * $discountPercentage) / 100);
        $salePriceAfterDiscount = round($salePrice - $discountAmount, 2);
        $profitAmountAfterDiscount = $salePriceAfterDiscount - $serviceProviderPriceAfterDiscount;

        $orderDateTime = Carbon::parse($request->order_date.' '.$request->order_time);
        $paymentSource = $request->payment_source;

        try {
            DB::beginTransaction();

            // ── Create booking ────────────────────────────────────────────────
            $booking = Booking::create([
                'service_id' => $service->id,
                'customer_id' => $customer->id,
                'coupon_id' => $couponId,
                'customer_address_id' => $address->id,
                'coupon_code' => $couponCode,
                'service_provider_price' => $serviceProviderPrice,
                'sale_price' => $salePrice,
                'profit_amount' => $profitAmount,
                'discount_percentage' => $discountPercentage,
                'discount_amount' => $discountAmount,
                'service_provider_price_after_discount' => $serviceProviderPriceAfterDiscount,
                'sale_price_after_discount' => $salePriceAfterDiscount,
                'profit_amount_after_discount' => $profitAmountAfterDiscount,
                'order_date' => $orderDateTime,
                'delivery_date' => null,
                'order_status' => 'pending',
            ]);

            $phone = MyFatoorahService::splitPhone($customer->phone);
            $customerReference = 'booking-'.$booking->id;
            $userDefinedField = 'booking_id:'.$booking->id;

            // ── SDK flow: no backend MyFatoorah call ──────────────────────────
            if ($paymentSource === 'sdk') {
                Payment::create([
                    'booking_id' => $booking->id,
                    'invoice_id' => null,
                    'amount' => $salePriceAfterDiscount,
                    'currency' => 'SAR',
                    'status' => 'pending',
                    'payment_source' => 'sdk',
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => __('responses.Booking created successfully. Proceed to payment.'),
                    'data' => [
                        'booking' => $booking->fresh(),
                        'payment' => [
                            'payment_source' => 'sdk',
                            'api_key' => env('APP_ENV') === 'local' ? config('services.myfatoorah.test_api_key') : config('services.myfatoorah.live_api_key'),
                            'amount' => $salePriceAfterDiscount,
                            'currency' => 'SAR',
                            'customer_reference' => $customerReference,
                            'user_defined_field' => $userDefinedField,
                            'customer_name' => $customer->name,
                            'customer_email' => $customer->email ?? 'noreply@fillo.app',
                            'mobile_country_code' => $phone['country_code'],
                            'customer_mobile' => $phone['mobile'],
                        ],
                    ],
                ], 201);
            }

            // ── Web flow: create a hosted invoice showing ALL payment methods ──
            $callbackBase = config('app.url').'/api/v1/myfatoorah';

            $mfResponse = app(MyFatoorahService::class)->sendPayment([
                'CustomerName' => $customer->name,
                'NotificationOption' => 'LNK',
                'DisplayCurrencyIso' => 'SAR',
                'MobileCountryCode' => $phone['country_code'],
                'CustomerMobile' => $phone['mobile'],
                'CustomerEmail' => $customer->email ?? 'noreply@fillo.app',
                'InvoiceValue' => $salePriceAfterDiscount,
                'CallBackUrl' => $callbackBase.'/callback',
                'ErrorUrl' => $callbackBase.'/error',
                'Language' => app()->getLocale() === 'ar' ? 'AR' : 'EN',
                'CustomerReference' => $customerReference,
                'CustomerCivilId' => $customer->national_address_short_number ?? '',
                'UserDefinedField' => $userDefinedField,
                'InvoiceItems' => [[
                    'ItemName' => $service->en_name ?? $service->ar_name,
                    'Quantity' => 1,
                    'UnitPrice' => $salePriceAfterDiscount,
                ]],
            ]);

            $invoiceId = (string) ($mfResponse['Data']['InvoiceId'] ?? '');
            $paymentUrl = $mfResponse['Data']['InvoiceURL'] ?? '';

            // ── Create pending payment record ─────────────────────────────────
            Payment::create([
                'booking_id' => $booking->id,
                'invoice_id' => $invoiceId,
                'amount' => $salePriceAfterDiscount,
                'currency' => 'SAR',
                'status' => 'pending',
                'payment_source' => 'web',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.Booking created successfully. Proceed to payment.'),
                'data' => [
                    'booking' => $booking->fresh(),
                    'payment' => [
                        'payment_source' => 'web',
                        'invoice_id' => $invoiceId,
                        'payment_url' => $paymentUrl,
                        'amount' => $salePriceAfterDiscount,
                        'currency' => 'SAR',
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function myBookings(Request $request)
    {
        $request->validate([
            'status' => 'sometimes|nullable|in:pending,confirmed,completed,all',
        ]);
        $customer = Auth::guard('customers')->user();
        $bookings = Booking::where('customer_id', $customer->id)
            ->with(['service', 'customerAddress', 'payment'])
            ->when($request->has('status') && $request->status != 'all', function ($query) use ($request) {
                $query->where('order_status', $request->status);
            })
            ->whereHas('payment', function ($query) {
                $query->where('status', '!=', 'pending');
            })
            ->orderBy('created_at', 'desc')
            ->paginate();

        return response()->json([
            'success' => true,
            'message' => __('responses.Bookings retrieved successfully'),
            'data' => $bookings,
        ], 200);
    }

    public function bookingDetails($booking_id)
    {
        $customer = Auth::guard('customers')->user();

        $booking = Booking::where('customer_id', $customer->id)
            ->with(['service', 'customerAddress', 'payment'])
            ->whereHas('payment', function ($query) {
                $query->where('status', '!=', 'pending');
            })
            ->findOrFail($booking_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.Booking details retrieved successfully'),
            'data' => $booking,
        ], 200);
    }

    public function cancelBooking($booking_id)
    {
        $customer = Auth::guard('customers')->user();

        $booking = Booking::where('customer_id', $customer->id)
            ->findOrFail($booking_id);

        if (in_array($booking->order_status, ['completed', 'cancelled', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => __('responses.Cannot cancel this booking'),
            ], 400);
        }

        DB::beginTransaction();

        try {
            $booking->update(['order_status' => 'cancelled']);

            // Attempt MyFatoorah refund if a completed payment exists.
            // MakeRefund requires MyFatoorah's PaymentId specifically — NOT
            // transaction_id (a different, shorter MF reference).
            $payment = $booking->payment;
            if ($payment && $payment->status === 'completed' && $payment->mf_payment_id) {
                try {
                    app(MyFatoorahService::class)->refundPayment(
                        $payment,
                        'Customer cancelled booking #'.$booking->id
                    );
                    $payment->update([
                        'status' => 'refunded',
                        'refunded_amount' => $payment->amount,
                    ]);
                } catch (\Exception $refundEx) {
                    // Log and continue — admin can handle manually via dashboard
                    Log::error('MyFatoorah refund failed on booking cancel', [
                        'booking_id' => $booking->id,
                        'payment_id' => $payment->id,
                        'error' => $refundEx->getMessage(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.Booking cancelled successfully'),
                'data' => $booking->fresh(),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function rateService(Request $request, $booking_id)
    {
        $request->validate([
            'rate' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ], [
            'rate' => __('responses.The rate must be between 1 and 5'),
        ]);

        $customer = Auth::guard('customers')->user();

        $booking = Booking::findOrFail($booking_id);

        if ($booking->customer_id != $customer->id) {
            return response()->json([
                'success' => false,
                'message' => __('responses.You are not authorized to rate this service'),
            ], 400);
        }
        if ($booking->order_status != 'completed') {
            return response()->json([
                'success' => false,
                'message' => __('responses.Cannot rate this service'),
            ], 400);
        }
        $service = Service::findOrFail($booking->service_id);
        if ($service->rates()->where('customer_id', $customer->id)->where('booking_id', $booking->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('responses.You have already rated this service'),
            ], 400);
        }
        DB::beginTransaction();
        try {
            $rate = Rate::create([
                'customer_id' => $customer->id,
                'rateable_id' => $service->id,
                'rateable_type' => 'App\Models\Service',
                'rate' => $request->rate,
                'comment' => $request->comment,
                'booking_id' => $booking->id,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.Service rated successfully'),
                'data' => $rate->refresh(),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }
}
