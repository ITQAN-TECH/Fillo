<?php

namespace App\Http\Controllers\api\v1\customers;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderCancellationRequest;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Rate;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $customer = Auth::guard('customers')->user();
        $request->validate([
            'status' => 'sometimes|nullable|in:pending,confirmed,shipping,delivered,completed,cancelled,refunded,all',
        ]);

        $orders = Order::where('customer_id', $customer->id)
            ->when($request->has('status') && $request->status != 'all', function ($query) use ($request) {
                $query->where('order_status', $request->status);
            })
            ->latest()
            ->paginate();

        return response()->json([
            'success' => true,
            'message' => __('responses.all orders'),
            'orders' => $orders,
        ], 200);
    }

    public function show($order_id)
    {
        $customer = Auth::guard('customers')->user();
        $order = Order::where('customer_id', $customer->id)->findOrFail($order_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.order details'),
            'order' => $order,
        ], 200);
    }

    public function store(Request $request)
    {
        $customer = Auth::guard('customers')->user();

        if (! $customer->national_address_short_number) {
            return response()->json([
                'success' => false,
                'message' => __('responses.national address short number is required'),
            ], 400);
        }

        $request->validate([
            'customer_address_id' => ['required', 'exists:customer_addresses,id'],
            'coupon_code' => ['nullable', 'string', 'exists:coupons,code'],
        ]);

        $cartItems = Cart::where('customer_id', $customer->id)->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => __('responses.cart is empty'),
            ], 400);
        }

        $customerAddress = $customer->addresses()->findOrFail($request->customer_address_id);

        try {
            DB::beginTransaction();

            foreach ($cartItems as $cartItem) {
                $productVariant = ProductVariant::where('id', $cartItem->product_variant_id)->lockForUpdate()->first();

                if (! $productVariant || ! $productVariant->status || ! $cartItem->product->status) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => __('responses.Product variant is not available'),
                    ], 400);
                }

                if ($productVariant->quantity < $cartItem->quantity) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => __('responses.Product variant quantity is not available'),
                    ], 400);
                }
            }

            $subtotalPrice = $cartItems->sum('total_price');
            $discountPercentage = 0;
            $discountAmount = 0;
            $couponId = null;
            $couponCode = null;

            if ($request->coupon_code) {
                $coupon = Coupon::whereIn('type', ['product', 'both_products_and_services'])
                    ->where('code', $request->coupon_code)
                    ->where('status', true)
                    ->where('expiry_date', '>', now())
                    ->first();

                if ($coupon) {
                    $discountPercentage = $coupon->discount_percentage;
                    $discountAmount = ($subtotalPrice * $discountPercentage) / 100;
                    $couponId = $coupon->id;
                    $couponCode = $coupon->code;
                }
            }

            $subtotalPriceAfterDiscount = $subtotalPrice - $discountAmount;
            $shippingFee = Setting::first()?->shipping_fee ?? 0;
            $totalPrice = $subtotalPriceAfterDiscount + $shippingFee;

            $orderNumber = 'ORD-'.strtoupper(uniqid());

            $order = Order::create([
                'customer_id' => $customer->id,
                'customer_address_id' => $customerAddress->id,
                'country_id' => $customerAddress->country_id,
                'city_id' => $customerAddress->city_id,
                'full_address' => $customerAddress->full_address,
                'phone' => $customer->phone,
                'national_address_short_number' => $customer->national_address_short_number,
                'coupon_id' => $couponId,
                'coupon_code' => $couponCode,
                'order_number' => $orderNumber,
                'subtotal_price' => $subtotalPrice,
                'discount_percentage' => $discountPercentage,
                'discount_amount' => $discountAmount,
                'subtotal_price_after_discount' => $subtotalPriceAfterDiscount,
                'shipping_fee' => $shippingFee,
                'total_price' => $totalPrice,
                'order_status' => 'pending',
            ]);

            foreach ($cartItems as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'product_variant_id' => $cartItem->product_variant_id,
                    'price' => $cartItem->price,
                    'quantity' => $cartItem->quantity,
                    'total_price' => $cartItem->total_price,
                ]);

                $productVariant = ProductVariant::find($cartItem->product_variant_id);
                $productVariant->decrement('quantity', $cartItem->quantity);
            }

            Payment::create([
                'order_id' => $order->id,
                'payment_method' => 'pending',
                'amount' => $totalPrice,
                'currency' => 'SAR',
                'status' => 'pending',
            ]);

            Cart::where('customer_id', $customer->id)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.order created successfully'),
                'order' => $order->fresh(),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function rateOrder(Request $request, $order_id)
    {
        $request->validate([
            'rate' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ], [
            'rate' => __('responses.The rate must be between 1 and 5'),
        ]);

        $customer = Auth::guard('customers')->user();

        $order = Order::findOrFail($order_id);

        if ($order->customer_id != $customer->id) {
            return response()->json([
                'success' => false,
                'message' => __('responses.You are not authorized to rate this order'),
            ], 400);
        }
        if ($order->order_status != 'completed') {
            return response()->json([
                'success' => false,
                'message' => __('responses.Cannot rate this order'),
            ], 400);
        }
        $order = Order::findOrFail($order_id);
        if ($order->rates()->where('customer_id', $customer->id)->where('order_id', $order->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('responses.You have already rated this order'),
            ], 400);
        }
        DB::beginTransaction();
        try {
            $uniqueProducts = $order->items->unique('product_id');
            foreach ($uniqueProducts as $item) {
                Rate::create([
                    'customer_id' => $customer->id,
                    'rateable_id' => $item->product_id,
                    'rateable_type' => 'App\Models\Product',
                    'rate' => $request->rate,
                    'comment' => $request->comment,
                    'order_id' => $order->id,
                ]);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.Order rated successfully'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function requestCancellation(Request $request, $order_id)
    {
        $customer = Auth::guard('customers')->user();
        $order = Order::where('customer_id', $customer->id)->findOrFail($order_id);

        if (in_array($order->order_status, ['completed', 'refunded', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => __('responses.cannot request cancellation for this order'),
            ], 400);
        }

        $existingRequest = OrderCancellationRequest::where('order_id', $order->id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => __('responses.cancellation request already exists'),
            ], 400);
        }

        $request->validate([
            'customer_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::beginTransaction();

            $cancellationRequest = OrderCancellationRequest::create([
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'customer_reason' => $request->customer_reason,
                'status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.cancellation request created successfully'),
                'cancellation_request' => $cancellationRequest,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function myCancellationRequests()
    {
        $customer = Auth::guard('customers')->user();

        $cancellationRequests = OrderCancellationRequest::where('customer_id', $customer->id)
            ->latest()
            ->paginate();

        return response()->json([
            'success' => true,
            'message' => __('responses.all cancellation requests'),
            'cancellation_requests' => $cancellationRequests,
        ], 200);
    }

    public function showCancellationRequest($request_id)
    {
        $customer = Auth::guard('customers')->user();

        $cancellationRequest = OrderCancellationRequest::where('customer_id', $customer->id)
            ->findOrFail($request_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.cancellation request details'),
            'cancellation_request' => $cancellationRequest,
        ], 200);
    }
}
