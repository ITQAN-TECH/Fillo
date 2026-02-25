<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SubCategoryController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-categories')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'search' => 'sometimes|nullable|string',
            'category_id' => 'sometimes|nullable|exists:categories,id',
        ]);
        $subCategories = SubCategory::when($request->has('category_id'), function ($query) use ($request) {
            $query->where('category_id', $request->category_id);
        })->when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('ar_title', 'like', '%'.$search.'%')
                    ->orWhere('en_title', 'like', '%'.$search.'%');
            });
        })->with('category')->latest()->paginate();
        $report = [];
        $report['sub_categories_count'] = SubCategory::count();
        $report['active_sub_categories_count'] = SubCategory::where('status', true)->count();
        $report['inactive_sub_categories_count'] = SubCategory::where('status', false)->count();

        return response()->json([
            'success' => true,
            'message' => __('responses.all sub categories'),
            'report' => $report,
            'sub_categories' => $subCategories,
        ]);
    }

    public function search(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-categories')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'search' => 'nullable|string',
            'category_id' => 'sometimes|nullable|exists:categories,id',
        ]);
        $subCategories = SubCategory::when($request->has('category_id'), function ($query) use ($request) {
            $query->where('category_id', $request->category_id);
        })->when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('ar_title', 'like', '%'.$search.'%')
                    ->orWhere('en_title', 'like', '%'.$search.'%');
            });
        })->with('category')->latest()->limit(10)->get();

        return response()->json([
            'success' => true,
            'message' => __('responses.all sub categories'),
            'sub_categories' => $subCategories,
        ]);
    }

    public function show($subCategory_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-categories')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $subCategory = SubCategory::findOrFail($subCategory_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.sub category'),
            'sub_category' => $subCategory,
        ]);
    }

    public function store(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('create-categories')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'ar_title' => 'required|string|max:255',
            'en_title' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:7168',
            'category_id' => 'required|exists:categories,id',
            'status' => 'nullable|boolean',
        ]);
        try {
            DB::beginTransaction();
            $subCategory = SubCategory::create([
                'ar_title' => $request->ar_title,
                'en_title' => $request->en_title,
                'category_id' => $request->category_id,
                'status' => $request->status ?? true,
            ]);
            if ($request->hasFile('image')) {
                $name = $request->image->hashName();
                $filename = time().'_'.uniqid().'_'.$name;
                $request->image->storeAs('public/media/', $filename);
                $subCategory->update([
                    'image' => $filename,
                ]);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'sub_category' => $subCategory,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function update(Request $request, $subCategory_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-categories')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'ar_title' => 'sometimes|nullable|string|max:255',
            'en_title' => 'sometimes|nullable|string|max:255',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:7168',
            'category_id' => 'sometimes|nullable|exists:categories,id',
        ]);
        $subCategory = SubCategory::findOrFail($subCategory_id);
        try {
            DB::beginTransaction();
            $subCategory->update([
                'ar_title' => $request->ar_title ?? $subCategory->ar_title,
                'en_title' => $request->en_title ?? $subCategory->en_title,
                'category_id' => $request->category_id ?? $subCategory->category_id,
            ]);
            if ($request->hasFile('image') && $request->image != null) {
                if ($subCategory->image) {
                    Storage::delete('public/media/'.$subCategory->image);
                }
                $name = $request->image->hashName();
                $filename = time().'_'.uniqid().'_'.$name;
                $request->image->storeAs('public/media/', $filename);
                $subCategory->update([
                    'image' => $filename,
                ]);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'sub_category' => $subCategory,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroy($subCategory_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-categories')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $subCategory = SubCategory::findOrFail($subCategory_id);
        try {
            $subCategory->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.sub category deleted successfully'),
                'sub_category' => $subCategory,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete sub category'),
            ], 400);
        }
    }

    public function changeStatus(Request $request, $subCategory_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-categories')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'status' => 'required|boolean',
        ]);
        $subCategory = SubCategory::findOrFail($subCategory_id);
        try {
            DB::beginTransaction();
            $subCategory->update([
                'status' => $request->status,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'sub_category' => $subCategory,
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
