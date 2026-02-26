<?php

namespace App\Http\Controllers\api\v1\customers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\Payment;
use App\Models\Rate;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    public function initiateBooking(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'customer_address_id' => 'required|exists:customer_addresses,id',
            'order_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'order_time' => 'required|date_format:H:i',
            'coupon_code' => 'nullable|string|exists:coupons,code',
        ], [
            'coupon_code' => __('responses.The coupon code is invalid or expired'),
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
        $salePriceAfterDiscount = $salePrice - $discountAmount;
        $profitAmountAfterDiscount = $salePriceAfterDiscount - $serviceProviderPriceAfterDiscount;

        $orderDateTime = Carbon::parse($request->order_date.' '.$request->order_time);

        return response()->json([
            'success' => true,
            'message' => __('responses.Booking details prepared. Proceed to payment.'),
            'data' => [
                'service_id' => $service->id,
                'service_name' => $service->en_name,
                'customer_address_id' => $address->id,
                'address' => $address->full_address,
                'order_date_time' => $orderDateTime->format('Y-m-d H:i:s'),
                'service_provider_price' => $serviceProviderPrice,
                'sale_price' => $salePrice,
                'profit_amount' => $profitAmount,
                'discount_percentage' => $discountPercentage,
                'discount_amount' => $discountAmount,
                'service_provider_price_after_discount' => $serviceProviderPriceAfterDiscount,
                'sale_price_after_discount' => $salePriceAfterDiscount,
                'profit_amount_after_discount' => $profitAmountAfterDiscount,
                'final_price' => $salePriceAfterDiscount,
                'coupon_id' => $couponId,
                'coupon_code' => $couponCode,
            ],
        ], 200);
    }

    public function payBooking(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'customer_address_id' => 'required|exists:customer_addresses,id',
            'order_date' => 'required|date|after_or_equal:today',
            'order_time' => 'required|date_format:H:i',
            'coupon_code' => 'nullable|string|exists:coupons,code',
            'payment_method' => 'required|string',
            'transaction_id' => 'nullable|string',
            'payment_response' => 'nullable|string',
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

        DB::beginTransaction();

        try {
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
                    DB::rollBack();

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

            $orderDateTime = Carbon::parse($request->order_date.' '.$request->order_time);

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

            $payment = Payment::create([
                'booking_id' => $booking->id,
                'payment_method' => $request->payment_method,
                'transaction_id' => $request->transaction_id,
                'amount' => $salePriceAfterDiscount,
                'currency' => 'SAR',
                'status' => 'completed',
                'payment_response' => $request->payment_response,
            ]);

            $booking->update(['order_status' => 'pending']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.Booking paid successfully'),
                'data' => [
                    'booking' => $booking->fresh(),
                    'payment' => $payment,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
                'details' => 'Failed to pay booking: '.$e->getMessage(),
            ], 400);
        }
    }

    public function myBookings()
    {
        $customer = Auth::guard('customers')->user();

        $bookings = Booking::where('customer_id', $customer->id)
            ->with(['service', 'customerAddress', 'payment'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

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

        $booking->update(['order_status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => __('responses.Booking cancelled successfully'),
            'data' => $booking->fresh(),
        ], 200);
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
