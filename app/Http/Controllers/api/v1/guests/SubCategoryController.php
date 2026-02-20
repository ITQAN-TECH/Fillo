<?php

namespace App\Http\Controllers\api\v1\guests;

use App\Http\Controllers\Controller;
use App\Models\SubCategory;
use Illuminate\Http\Request;

class SubCategoryController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'category_id' => 'sometimes|nullable|exists:categories,id',
        ]);
        $subCategories = SubCategory::when($request->has('category_id'), function ($query) use ($request) {
            $query->where('category_id', $request->category_id);
        })->where('status', true)->with('category')->latest()->get();

        return response()->json([
            'success' => true,
            'message' => __('responses.all sub categories'),
            'sub_categories' => $subCategories,
        ]);
    }

    public function show($subCategory_id)
    {
        $subCategory = SubCategory::where('status', true)->with('category')->findOrFail($subCategory_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.sub category'),
            'sub_category' => $subCategory,
        ]);
    }
}
