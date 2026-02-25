<?php

namespace App\Http\Controllers\api\v1\customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\services\JawalySMSService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|unique:customers,phone',
            'password' => 'required|string|max:255|confirmed',
        ]);
        $throttleResult = $this->throttle($request->ip(), 'register_one_minute', 1);
        if ($throttleResult !== true) {
            return $throttleResult;
        }
        $throttleResult = $this->throttle($request->ip(), 'register_ten_minute', 3, 10);
        if ($throttleResult !== true) {
            return $throttleResult;
        }
        try {
            DB::beginTransaction();
            $customer = Customer::create([
                'name' => 'مستخدم',
                'phone' => $request->phone,
                'password' => $request->password,
                'otp' => rand(1000, 9999),
            ]);

            // Send SMS
            // $message = "رمز التحقق : {$customer->otp}";
            // JawalySMSService::sendMessage($customer->phone, $message);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'redirect to set OTP',
                'customer' => $customer,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:255',
            'password' => 'required|string|max:255',
        ]);
        $customer = Customer::where('phone', $request->phone)->first();
        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('responses.credentials are incorrect'),
            ], 400);
        }
        if (! Hash::check($request->password, $customer->password)) {
            return response()->json([
                'success' => false,
                'message' => __('responses.credentials are incorrect'),
            ], 400);
        }
        if (! $customer->status) {
            return response()->json([
                'success' => false,
                'message' => __('responses.this customer is banned'),
            ], 400);
        }
        if (! $customer->is_phone_verified) {
            $otp = rand(1000, 9999);
            $customer->update([
                'otp' => $otp,
            ]);
            // Rate limit check
            $throttleResult = $this->throttle($request->ip(), 'login_one_minute', 1);
            if ($throttleResult !== true) {
                return $throttleResult;
            }
            $throttleResult = $this->throttle($request->ip(), 'login_ten_minute', 3, 10);
            if ($throttleResult !== true) {
                return $throttleResult;
            }

            // Send SMS
            // $message = "رمز التحقق : {$customer->otp}";
            // JawalySMSService::sendMessage($customer->phone, $message);

            return response()->json([
                'success' => true,
                'message' => 'redirect to set OTP',
                'customer' => $customer,
            ]);
        }

        $token = $customer->createToken('customer_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('responses.login successfully'),
            'customer' => $customer,
            'token' => $token,
        ]);
    }

    public function logout()
    {
        $customer = Auth::guard('customers')->user();
        $customer->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => __('responses.logout successfully'),
        ]);
    }

    public function setOTP(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:255',
            'otp' => 'required|string|size:4',
        ]);

        $customer = Customer::where('phone', $request->phone)->first();
        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('responses.customer not found'),
            ], 404);
        }
        if ($customer->otp != $request->otp) {
            return response()->json([
                'success' => false,
                'message' => __('responses.otp code is invalid'),
            ], 400);
        }
        if (! $customer->status) {
            return response()->json([
                'success' => false,
                'message' => __('responses.this customer is banned'),
            ], 400);
        }
        $customer->update([
            'otp' => null,
            'is_phone_verified' => true,
        ]);
        $token = $customer->createToken('customer_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'login successfully',
            'token' => $token,
            'customer' => $customer,
        ]);
    }

    public function resendOTP(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:255',
        ]);
        $otp = rand(1000, 9999);
        $customer = Customer::where('phone', $request->phone)->first();
        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('responses.customer not found'),
            ], 404);
        }
        // Rate limit check
        $throttleResult = $this->throttle($request->ip(), 'resend_otp_one_minute', 1);
        if ($throttleResult !== true) {
            return $throttleResult;
        }
        $throttleResult = $this->throttle($request->ip(), 'resend_otp_ten_minute', 3, 10);
        if ($throttleResult !== true) {
            return $throttleResult;
        }
        $customer->update([
            'otp' => $otp,
        ]);

        // Send SMS
        //  $message = "رمز التحقق : {$customer->otp}";
        //  JawalySMSService::sendMessage($customer->phone, $message);

        return response()->json([
            'success' => true,
            'message' => __('responses.otp send successfully'),
        ]);
    }
}
