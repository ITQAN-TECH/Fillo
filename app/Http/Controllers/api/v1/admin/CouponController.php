<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-coupons')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'search' => 'sometimes|nullable|string',
            'type' => 'sometimes|nullable|in:product,service,both_products_and_services',
        ]);
        $coupons = Coupon::when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('code', 'like', '%'.$search.'%');
            });
        })->when($request->has('type'), function ($query) use ($request) {
            $query->where('type', $request->type);
        })->latest()->paginate();
        $report = [];
        $report['coupons_count'] = Coupon::count();
        $report['active_coupons_count'] = Coupon::where('status', true)->count();
        $report['inactive_coupons_count'] = Coupon::where('status', false)->count();

        return response()->json([
            'success' => true,
            'message' => __('responses.all coupons'),
            'report' => $report,
            'coupons' => $coupons,
        ]);
    }

    public function show($coupon_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-coupons')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $coupon = Coupon::findOrFail($coupon_id);
        return response()->json([
            'success' => true,
            'message' => __('responses.coupon'),
            'coupon' => $coupon,
        ]);
    }

    public function store(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('create-coupons')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'code' => 'required|string|max:255|min:3|unique:coupons,code',
            'discount_percentage' => 'required|numeric|min:1|max:99',
            'expiry_date' => 'required|date_format:Y-m-d',
            'type' => 'required|in:product,service,both_products_and_services',
        ]);
        try {
            DB::beginTransaction();
            $coupon = Coupon::create([
                'code' => $request->code,
                'discount_percentage' => $request->discount_percentage,
                'expiry_date' => $request->expiry_date,
                'type' => $request->type,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.coupon created successfully'),
                'coupon' => $coupon,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function update(Request $request, $coupon_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-coupons')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'code' => 'sometimes|nullable|string|max:255|min:3|unique:coupons,code,' . $coupon_id,
            'discount_percentage' => 'sometimes|nullable|numeric|min:1|max:99',
            'expiry_date' => 'sometimes|nullable|date_format:Y-m-d',
            'type' => 'sometimes|nullable|in:product,service,both_products_and_services',
        ]);
        $coupon = Coupon::findOrFail($coupon_id);
        try {
            DB::beginTransaction();
            $coupon->update([
                'code' => $request->code ?? $coupon->code,
                'discount_percentage' => $request->discount_percentage ?? $coupon->discount_percentage,
                'expiry_date' => $request->expiry_date ?? $coupon->expiry_date,
                'type' => $request->type ?? $coupon->type,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.coupon updated successfully'),
                'coupon' => $coupon,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroy($coupon_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-coupons')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $coupon = Coupon::findOrFail($coupon_id);
        try {
            $coupon->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.coupon deleted successfully'),
                'coupon' => $coupon,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete coupon'),
            ], 400);
        }
    }

    public function changeStatus(Request $request, $coupon_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-coupons')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'status' => 'required|boolean',
        ]);
        $coupon = Coupon::findOrFail($coupon_id);
        try {
            DB::beginTransaction();
            $coupon->update([
                'status' => $request->status,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.coupon status changed successfully'),
                'coupon' => $coupon,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot change coupon status'),
            ], 400);
        }
    }
}
