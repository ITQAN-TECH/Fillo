<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function show()
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-settings')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $setting = Setting::firstOrFail();

        return response()->json([
            'success' => true,
            'message' => __('responses.setting'),
            'setting' => $setting,
        ]);
    }

    public function update(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-settings')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'phone_number' => 'sometimes|nullable|string',
            'email' => 'sometimes|nullable|email',
            'shipping_fee' => 'sometimes|nullable|numeric|min:0',
        ]);
        $setting = Setting::firstOrFail();
        try {
            DB::beginTransaction();
            $setting->update([
                'phone_number' => $request->phone_number ?? $setting->phone_number,
                'email' => $request->email ?? $setting->email,
                'shipping_fee' => $request->shipping_fee ?? $setting->shipping_fee,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'setting' => $setting,
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
