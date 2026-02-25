<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
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
            'type' => 'sometimes|nullable|in:service,product,all',
        ]);
        $categories = Category::when($request->has('type') && $request->type != 'all', function ($query) use ($request) {
            $query->where('type', $request->type);
        })->when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('ar_title', 'like', '%'.$search.'%')
                    ->orWhere('en_title', 'like', '%'.$search.'%');
            });
        })->with('subCategories')->latest()->paginate();
        $report = [];
        $report['categories_count'] = Category::count();
        $report['active_categories_count'] = Category::where('status', true)->count();
        $report['inactive_categories_count'] = Category::where('status', false)->count();
        $report['service_categories_count'] = Category::where('type', 'service')->count();
        $report['product_categories_count'] = Category::where('type', 'product')->count();

        return response()->json([
            'success' => true,
            'message' => __('responses.all categories'),
            'report' => $report,
            'categories' => $categories,
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
            'type' => 'sometimes|nullable|in:service,product,all',
        ]);
        $categories = Category::when($request->has('type') && $request->type != 'all', function ($query) use ($request) {
            $query->where('type', $request->type);
        })->when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('ar_title', 'like', '%'.$search.'%')
                    ->orWhere('en_title', 'like', '%'.$search.'%');
            });
        })->with('subCategories')->latest()->limit(10)->get();

        return response()->json([
            'success' => true,
            'message' => __('responses.all categories'),
            'categories' => $categories,
        ]);
    }

    public function show($category_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-categories')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $category = Category::findOrFail($category_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.category'),
            'category' => $category,
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
            'type' => 'required|string|in:service,product',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:7168',
            'status' => 'nullable|boolean',
        ]);
        try {
            DB::beginTransaction();
            $category = Category::create([
                'ar_title' => $request->ar_title,
                'en_title' => $request->en_title,
                'type' => $request->type,
                'status' => $request->status ?? true,
            ]);
            if ($request->hasFile('image')) {
                $name = $request->image->hashName();
                $filename = time().'_'.uniqid().'_'.$name;
                $request->image->storeAs('public/media/', $filename);
                $category->update([
                    'image' => $filename,
                ]);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'category' => $category,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function update(Request $request, $category_id)
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
            'type' => 'sometimes|nullable|string|in:service,product',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:7168',
        ]);
        $category = Category::findOrFail($category_id);
        try {
            DB::beginTransaction();
            $category->update([
                'ar_title' => $request->ar_title ?? $category->ar_title,
                'en_title' => $request->en_title ?? $category->en_title,
                'type' => $request->type ?? $category->type,
            ]);
            if ($request->hasFile('image') && $request->image != null) {
                if ($category->image) {
                    Storage::delete('public/media/'.$category->image);
                }
                $name = $request->image->hashName();
                $filename = time().'_'.uniqid().'_'.$name;
                $request->image->storeAs('public/media/', $filename);
                $category->update([
                    'image' => $filename,
                ]);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'category' => $category,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroy($category_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-categories')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $category = Category::findOrFail($category_id);
        try {
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.category deleted successfully'),
                'category' => $category,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete category'),
            ], 400);
        }
    }

    public function changeStatus(Request $request, $category_id)
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
        $category = Category::findOrFail($category_id);
        try {
            DB::beginTransaction();
            $category->update([
                'status' => $request->status,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'category' => $category,
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
