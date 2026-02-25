<?php

namespace App\Http\Controllers\api\v1\guests;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'sometimes|nullable|string',
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'sub_category_id' => 'sometimes|nullable|exists:sub_categories,id',
            'per_page' => 'sometimes|nullable|integer|min:1|max:100',
        ]);
        $products = Product::when($request->has('category_id'), function ($query) use ($request) {
            $query->where('category_id', $request->category_id);
        })->when($request->has('sub_category_id'), function ($query) use ($request) {
            $query->where('sub_category_id', $request->sub_category_id);
        })->when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where('ar_name', 'like', '%'.$search.'%')
                ->orWhere('en_name', 'like', '%'.$search.'%')
                ->orWhere('ar_description', 'like', '%'.$search.'%')
                ->orWhere('en_description', 'like', '%'.$search.'%')
                ->orWhere('ar_small_description', 'like', '%'.$search.'%')
                ->orWhere('en_small_description', 'like', '%'.$search.'%');
        })->isActive()->latest()->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'message' => __('responses.all products'),
            'products' => $products,
        ]);
    }

    public function show($product_id)
    {
        $product = Product::with(['variants.color', 'variants.size', 'images'])
            ->isActive()
            ->findOrFail($product_id);

        $rates = $product->rates()->with('customer')->latest()->paginate();

        $starCounts = [];
        $starPercentages = [];
        $totalRates = $product->rates()->count();
        for ($i = 1; $i <= 5; $i++) {
            $count = $product->rates()->where('rate', $i)->count();
            $starCounts[$i] = $count;
            $starPercentages[$i] = $totalRates > 0 ? round(($count / $totalRates) * 100, 2) : 0;
        }

        $rate_stats = [
            '1' => ['count' => $starCounts[1], 'percentage' => $starPercentages[1]],
            '2' => ['count' => $starCounts[2], 'percentage' => $starPercentages[2]],
            '3' => ['count' => $starCounts[3], 'percentage' => $starPercentages[3]],
            '4' => ['count' => $starCounts[4], 'percentage' => $starPercentages[4]],
            '5' => ['count' => $starCounts[5], 'percentage' => $starPercentages[5]],
        ];

        $variantsBySizes = $product->variants()
            ->where('status', true)
            ->where('quantity', '>', 0)
            ->with(['color', 'size'])
            ->get()
            ->groupBy('size_id')
            ->map(function ($sizeGroup) {
                $size = $sizeGroup->first()->size;

                return [
                    'size_id' => $size->id,
                    'size_name_ar' => $size->ar_name,
                    'size_name_en' => $size->en_name,
                    'size_code' => $size->code,
                    'colors' => $sizeGroup->map(function ($variant) {
                        return [
                            'variant_id' => $variant->id,
                            'color_id' => $variant->color->id,
                            'color_name_ar' => $variant->color->ar_name,
                            'color_name_en' => $variant->color->en_name,
                            'hex_code' => $variant->color->hex_code,
                            'quantity' => $variant->quantity,
                            'converted_sale_price' => $variant->converted_sale_price,
                            'variant_sku' => $variant->variant_sku,
                        ];
                    })->values(),
                ];
            })->values();

        $productData = [
            'product' => $product->unsetRelation('variants'),
            'sizes_with_colors' => $variantsBySizes,
        ];

        return response()->json([
            'success' => true,
            'message' => __('responses.product'),
            'product' => $productData,
            'rate_stats' => $rate_stats,
            'rates' => $rates,
        ]);
    }
}
