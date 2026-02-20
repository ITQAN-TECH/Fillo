<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|exists:users,email|max:255',
            'password' => 'required|string|min:8|max:255',
        ]);
        $email = $request->email;
        $password = $request->password;
        $admin = User::where('email', $email)->firstOrFail();
        try {
            DB::beginTransaction();
            if (! Hash::check($password, $admin->password)) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.credentials are incorrect'),
                ], 400);
            }
            if (! $admin->status) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.this admin is banned'),
                ], 400);
            }
            $token = $admin->createToken('admin_token')->plainTextToken;
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.login successfully'),
                'admin' => $admin,
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function logout()
    {
        $user = Auth::guard('admins')->user();
        $user->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => __('responses.logout successfully'),
        ]);
    }
}
