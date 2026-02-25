<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SizeController extends Controller
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

        $sizes = Size::when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('ar_name', 'like', '%'.$search.'%')
                    ->orWhere('en_name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        })
            ->when($request->has('status') && $request->status != 'all', function ($query) use ($request) {
                $query->where('status', $request->status == 'active' ? true : false);
            })
            ->latest()
            ->paginate();

        return response()->json([
            'success' => true,
            'message' => __('responses.all sizes'),
            'sizes' => $sizes,
        ]);
    }

    public function show($size_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $size = Size::findOrFail($size_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.size'),
            'size' => $size,
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
            'code' => 'required|string|unique:sizes,code',
        ]);

        try {
            DB::beginTransaction();

            $size = Size::create([
                'ar_name' => $request->ar_name,
                'en_name' => $request->en_name,
                'code' => $request->code,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.size created successfully'),
                'size' => $size,
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

    public function update(Request $request, $size_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $size = Size::findOrFail($size_id);

        $request->validate([
            'ar_name' => 'sometimes|nullable|string',
            'en_name' => 'sometimes|nullable|string',
            'code' => 'sometimes|nullable|string|unique:sizes,code,'.$size->id,
        ]);

        try {
            DB::beginTransaction();

            $size->update([
                'ar_name' => $request->ar_name ?? $size->ar_name,
                'en_name' => $request->en_name ?? $size->en_name,
                'code' => $request->code ?? $size->code,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'size' => $size,
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

    public function destroy($size_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $size = Size::findOrFail($size_id);

        if ($size->productVariants()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => __('responses.cannot delete size with products'),
            ], 400);
        }

        try {
            DB::beginTransaction();
            $size->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.size deleted successfully'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete size'),
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function changeStatus(Request $request, $size_id)
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

        $size = Size::findOrFail($size_id);

        try {
            DB::beginTransaction();
            $size->update([
                'status' => $request->status,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.size status changed successfully'),
                'size' => $size,
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
