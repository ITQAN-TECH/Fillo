<?php

namespace App\Http\Controllers\api\v1\guests;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
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
        })->where('status', true)->with('subCategories')->latest()->get();

        return response()->json([
            'success' => true,
            'message' => __('responses.all categories'),
            'categories' => $categories,
        ]);
    }

    public function show($category_id)
    {
        $category = Category::where('status', true)->with('subCategories')->findOrFail($category_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.category'),
            'category' => $category,
        ]);
    }
}
