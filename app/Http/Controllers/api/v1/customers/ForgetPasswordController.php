<?php

namespace App\Http\Controllers\api\v1\customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\services\JawalySMSService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ForgetPasswordController extends Controller
{
    public function setPhone(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:255',
        ]);
        $throttleResult = $this->throttle($request->ip(), 'forget_password_one_minute', 3);
        if ($throttleResult !== true) {
            return $throttleResult;
        }
        $throttleResult = $this->throttle($request->ip(), 'forget_password_ten_minute', 10, 10);
        if ($throttleResult !== true) {
            return $throttleResult;
        }
        try {
            DB::beginTransaction();
            $customer = Customer::where('phone', $request->phone)->first();
            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.customer not found'),
                ], 404);
            }
            if (! $customer->status) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.this customer is banned'),
                ], 400);
            }
            $otp = rand(1000, 9999);
            $customer->update([
                'otp' => $otp,
            ]);
            // Send SMS
            // $message = "رمز التحقق : {$customer->otp}";
            // JawalySMSService::sendMessage($customer->phone, $message);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.otp send successfully'),
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
        $customer->update([
            'is_phone_verified' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'redirect to change password',
            'customer' => $customer,
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:255',
            'password' => 'required|string|max:255|confirmed',
            'otp' => 'required|string|size:4',
        ]);
        $customer = Customer::where('phone', $request->phone)->first();
        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('responses.credentials are incorrect'),
            ], 400);
        }
        if ($customer->otp != $request->otp) {
            return response()->json([
                'success' => false,
                'message' => __('responses.otp code is invalid'),
            ], 400);
        }

        $customer->update([
            'password' => $request->password,
            'otp' => null,
            'is_phone_verified' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('responses.password changed successfully'),
            'customer' => $customer,
        ]);
    }
}
