<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendOtpJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ForgetPasswordController extends Controller
{
    public function sendCodeForForgetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|string|exists:users,email|max:255',
        ]);
        try {
            DB::beginTransaction();
            $admin = User::where('email', $request->email)->first();
            if (! $admin) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.account not found'),
                ], 404);
            }
            $otp = rand(1111, 9999);
            $admin->update([
                'otp' => $otp,
            ]);
            dispatch(new SendOtpJob($admin, $otp));
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.otp code send successfully'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function setOTPForForgetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|string|exists:users,email|max:255',
            'otp' => 'required|size:4',
        ]);
        try {
            DB::beginTransaction();
            $admin = User::where('email', $request->email)->first();
            if (! $admin) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.account not found'),
                ], 404);
            }
            if ($request->otp != $admin->otp) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.otp code is not correct'),
                ], 400);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.redirect to change password'),
                'admin' => $admin,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'email' => 'required|string|exists:users,email|max:255',
            'password' => 'required|string|min:8|max:255|confirmed',
            'otp' => 'required|size:4',
        ]);
        try {
            DB::beginTransaction();
            $admin = User::where('email', $request->email)->first();
            if (! $admin) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.account not found'),
                ], 404);
            }
            if ($request->otp == $admin->otp) {
                $admin->update([
                    'otp' => null,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.otp code is not correct'),
                ], 400);
            }
            $admin->update([
                'password' => $request->password,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.password changed,redirect to login page'),
                'admin' => $admin,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }
}
