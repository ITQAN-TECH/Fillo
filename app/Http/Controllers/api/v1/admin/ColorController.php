<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Color;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ColorController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $request->validate([
            'search' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|in:active,inactive,all',
        ]);

        $colors = Color::when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('ar_name', 'like', '%'.$search.'%')
                    ->orWhere('en_name', 'like', '%'.$search.'%')
                    ->orWhere('hex_code', 'like', '%'.$search.'%');
            });
        })
            ->when($request->has('status') && $request->status != 'all', function ($query) use ($request) {
                $query->where('status', $request->status == 'active' ? true : false);
            })
            ->latest()
            ->paginate();

        return response()->json([
            'success' => true,
            'message' => __('responses.all colors'),
            'colors' => $colors,
        ]);
    }

    public function show($color_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $color = Color::findOrFail($color_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.color'),
            'color' => $color,
        ]);
    }

    public function store(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('create-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $request->validate([
            'ar_name' => 'required|string',
            'en_name' => 'required|string',
            'hex_code' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
        ]);

        try {
            DB::beginTransaction();

            $color = Color::create([
                'ar_name' => $request->ar_name,
                'en_name' => $request->en_name,
                'hex_code' => $request->hex_code,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.color created successfully'),
                'color' => $color,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function update(Request $request, $color_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $color = Color::findOrFail($color_id);

        $request->validate([
            'ar_name' => 'sometimes|nullable|string',
            'en_name' => 'sometimes|nullable|string',
            'hex_code' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
        ]);

        try {
            DB::beginTransaction();

            $color->update([
                'ar_name' => $request->ar_name ?? $color->ar_name,
                'en_name' => $request->en_name ?? $color->en_name,
                'hex_code' => $request->hex_code ?? $color->hex_code,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'color' => $color,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function destroy($color_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $color = Color::findOrFail($color_id);

        if ($color->productVariants()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => __('responses.cannot delete color with products'),
            ], 400);
        }

        try {
            DB::beginTransaction();
            $color->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.color deleted successfully'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete color'),
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function changeStatus(Request $request, $color_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $request->validate([
            'status' => 'required|boolean',
        ]);

        $color = Color::findOrFail($color_id);

        try {
            DB::beginTransaction();
            $color->update([
                'status' => $request->status,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.color status changed successfully'),
                'color' => $color,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }
}
