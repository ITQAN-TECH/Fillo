<?php

namespace App\Http\Controllers\api\v1\customers;

use App\Facades\Currency;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function show(Request $request)
    {
        $request->validate([
            'coupon_code' => ['nullable', 'string', 'exists:coupons,code'],
        ]);
        $customer = Auth::guard('customers')->user();
        $cart = Cart::where('customer_id', $customer->id)->get();
        $totalPrice = $cart->sum('total_price');
        $discountPercentage = 0;
        $discountAmount = 0;
        $couponId = null;
        $couponCode = null;
        if ($request->coupon_code) {
            $coupon = Coupon::whereIn('type', ['product', 'both_products_and_services'])->where('code', $request->coupon_code)->where('status', true)->where('expiry_date', '>', now())->first();
            if ($coupon) {
                $discountPercentage = $coupon->discount_percentage;
                $discountAmount = ($totalPrice * $discountPercentage) / 100;
                $couponId = $coupon->id;
                $couponCode = $coupon->code;
            }
        }
        $totalPriceAfterDiscount = $totalPrice - $discountAmount;
        $shippingFee = Setting::first()?->shipping_fee ?? 0;
        $totalPriceAfterDiscountAndShippingFee = $totalPriceAfterDiscount + $shippingFee;
        $rate = Currency::getRate('SAR');

        return response()->json([
            'success' => true,
            'message' => __('responses.Cart items'),
            'total_price' => round($totalPrice * $rate, 2),
            'discount_percentage' => $discountPercentage,
            'discount_amount' => round($discountAmount * $rate, 2),
            'shipping_fee' => round($shippingFee * $rate, 2),
            'coupon_id' => $couponId,
            'coupon_code' => $couponCode,
            'total_price_after_discount' => round($totalPriceAfterDiscount * $rate, 2),
            'total_price_after_discount_and_shipping_fee' => round($totalPriceAfterDiscountAndShippingFee * $rate, 2),
            'cart' => $cart,
        ], 200);
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);
        $customer = Auth::guard('customers')->user();
        $productVariant = ProductVariant::where('id', $request->product_variant_id)->where('status', true)->firstOrFail();
        $product = Product::where('id', $productVariant->product_id)->where('status', true)->firstOrFail();
        try {
            $cart = Cart::where('customer_id', $customer->id)->where('product_id', $product->id)->where('product_variant_id', $productVariant->id)->first();
            if (! $productVariant->status || ! $product->status || $productVariant->quantity <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.Product variant is not available'),
                ], 400);
            }
            if ($cart) {
                if ($productVariant->quantity < $request->quantity + $cart->quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => __('responses.Product variant quantity is not available'),
                    ], 400);
                }
                $cart->update([
                    'quantity' => $request->quantity + $cart->quantity,
                    'price' => $productVariant->sale_price,
                    'total_price' => $productVariant->sale_price * ($request->quantity + $cart->quantity),
                ]);
            } else {
                if ($productVariant->quantity < $request->quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => __('responses.Product variant quantity is not available'),
                    ], 400);
                }
                $cart = Cart::create([
                    'customer_id' => $customer->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $productVariant->id,
                    'quantity' => $request->quantity,
                    'price' => $productVariant->sale_price,
                    'total_price' => $productVariant->sale_price * $request->quantity,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => __('responses.Product added to Cart successfully!.'),
                'cart' => $cart->refresh(),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function plus(Request $request, $cart_id)
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);
        $cart = Cart::where('customer_id', Auth::guard('customers')->id())->where('id', $cart_id)->firstOrFail();
        if ($cart->productVariant->quantity < $cart->quantity + $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => __('responses.Product variant quantity is not available'),
            ], 400);
        }
        $cart->update(['quantity' => $cart->quantity + $request->quantity]);

        return response()->json([
            'success' => true,
            'message' => __('responses.Product quantity increased successfully!.'),
            'cart' => $cart->refresh(),
        ], 200);
    }

    public function minus(Request $request, $cart_id)
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);
        $cart = Cart::where('customer_id', Auth::guard('customers')->id())->where('id', $cart_id)->firstOrFail();
        if ($cart->quantity <= $request->quantity) {
            $cart->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.Product removed from Cart successfully!.'),
            ], 200);
        }
        $cart->update(['quantity' => $cart->quantity - $request->quantity]);

        return response()->json([
            'success' => true,
            'message' => __('responses.Product removed from Cart successfully!.'),
            'cart' => $cart->refresh(),
        ], 200);
    }

    public function destroy($cart_id)
    {
        $cart = Cart::where('customer_id', Auth::guard('customers')->id())->where('id', $cart_id)->firstOrFail();
        try {
            $cart->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.Product removed from Cart successfully!.'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroyAll()
    {
        try {
            Cart::where('customer_id', Auth::guard('customers')->id())->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.Cart deleted successfully'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }
}
