<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
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

        $products = Product::with(['variants.color', 'variants.size'])
            ->when($request->has('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($query) use ($search) {
                    $query->where('ar_name', 'like', '%'.$search.'%')
                        ->orWhere('en_name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%');
                });
            })
            ->when($request->has('status') && $request->status != 'all', function ($query) use ($request) {
                $query->where('status', $request->status == 'active' ? true : false);
            })
            ->latest()
            ->paginate();

        $report = [
            'products_count' => Product::count(),
            'active_products_count' => Product::where('status', true)->count(),
            'inactive_products_count' => Product::where('status', false)->count(),
            'total_variants' => ProductVariant::count(),
            'out_of_stock_count' => ProductVariant::where('quantity', 0)->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => __('responses.all products'),
            'report' => $report,
            'products' => $products,
        ]);
    }

    public function show($product_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $product = Product::with(['variants.color', 'variants.size', 'images'])->findOrFail($product_id);
        $rates = $product->rates()->with('customer')->latest()->paginate();

        return response()->json([
            'success' => true,
            'message' => __('responses.product'),
            'product' => $product,
            'rates' => $rates,
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
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'required|exists:sub_categories,id',
            'ar_name' => 'required|string',
            'en_name' => 'required|string',
            'ar_description' => 'nullable|string',
            'en_description' => 'nullable|string',
            'ar_small_description' => 'nullable|string',
            'en_small_description' => 'nullable|string',
            'sku' => 'required|string|unique:products,sku',
            'sale_price' => 'required|numeric|min:0',
            'images' => 'required|array',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:7168',
            'variants' => 'required|array|min:1',
            'variants.*.color_id' => 'required|exists:colors,id',
            'variants.*.size_id' => 'required|exists:sizes,id',
            'variants.*.quantity' => 'required|integer|min:0',
            'variants.*.sale_price' => 'nullable|numeric|min:0',
            'features' => 'sometimes|nullable|array',
            'features.*' => 'sometimes|nullable|array',
            'features.*.ar_title' => 'sometimes|nullable|string',
            'features.*.en_title' => 'sometimes|nullable|string',
            'features.*.ar_description' => 'sometimes|nullable|string',
            'features.*.en_description' => 'sometimes|nullable|string',
        ]);
        $category = Category::where('type', 'product')->findOrFail($request->category_id);
        $subCategory = SubCategory::findOrFail($request->sub_category_id);
        if ($subCategory->category_id != $category->id) {
            return response()->json([
                'success' => false,
                'message' => __('responses.sub category does not belong to category'),
            ], 400);
        }
        try {
            DB::beginTransaction();

            $productData = [
                'category_id' => $request->category_id,
                'sub_category_id' => $request->sub_category_id,
                'ar_name' => $request->ar_name,
                'en_name' => $request->en_name,
                'ar_description' => $request->ar_description,
                'en_description' => $request->en_description,
                'ar_small_description' => $request->ar_small_description,
                'en_small_description' => $request->en_small_description,
                'sku' => $request->sku,
                'sale_price' => $request->sale_price ?? 0,
            ];

            $product = Product::create($productData);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $imageName = time().'_'.uniqid().'_'.$image->hashName();
                    $image->storeAs('public/media/', $imageName);
                    $product->images()->create([
                        'image' => $imageName,
                    ]);
                }
            }

            foreach ($request->variants as $variantData) {
                $variant_sku = $request->sku.'-C'.$variantData['color_id'].'-S'.$variantData['size_id'];

                ProductVariant::create([
                    'product_id' => $product->id,
                    'color_id' => $variantData['color_id'],
                    'size_id' => $variantData['size_id'],
                    'quantity' => $variantData['quantity'],
                    'sale_price' => $variantData['sale_price'] ?? 0,
                    'variant_sku' => $variant_sku,
                ]);
            }

            if ($request->has('features') && $request->features != null) {
                foreach ($request->features as $feature) {
                    $product->features()->create([
                        'ar_title' => $feature['ar_title'],
                        'en_title' => $feature['en_title'],
                        'ar_description' => $feature['ar_description'],
                        'en_description' => $feature['en_description'],
                    ]);
                }
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.product created successfully'),
                'product' => $product->load(['variants.color', 'variants.size', 'images', 'category', 'subCategory', 'features']),
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

    public function update(Request $request, $product_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $product = Product::findOrFail($product_id);

        $request->validate([
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'sub_category_id' => 'sometimes|nullable|exists:sub_categories,id',
            'ar_name' => 'sometimes|nullable|string',
            'en_name' => 'sometimes|nullable|string',
            'ar_small_description' => 'nullable|string',
            'en_small_description' => 'nullable|string',
            'ar_description' => 'nullable|string',
            'en_description' => 'nullable|string',
            'sku' => 'sometimes|nullable|string|unique:products,sku,'.$product->id,
            'sale_price' => 'sometimes|nullable|numeric|gt:0',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:7168',
            'features' => 'sometimes|nullable|array',
            'features.*' => 'sometimes|nullable|array',
            'features.*.ar_title' => 'sometimes|nullable|string',
            'features.*.en_title' => 'sometimes|nullable|string',
            'features.*.ar_description' => 'sometimes|nullable|string',
            'features.*.en_description' => 'sometimes|nullable|string',
        ]);
        $category = Category::where('type', 'product')->findOrFail($request->category_id ?? $product->category_id);
        $subCategory = SubCategory::findOrFail($request->sub_category_id ?? $product->sub_category_id);
        if ($subCategory->category_id != $category->id) {
            return response()->json([
                'success' => false,
                'message' => __('responses.sub category does not belong to category'),
            ], 400);
        }
        try {
            DB::beginTransaction();

            $updateData = [
                'category_id' => $request->category_id ?? $product->category_id,
                'sub_category_id' => $request->sub_category_id ?? $product->sub_category_id,
                'ar_name' => $request->ar_name ?? $product->ar_name,
                'en_name' => $request->en_name ?? $product->en_name,
                'ar_description' => $request->ar_description ?? $product->ar_description,
                'en_description' => $request->en_description ?? $product->en_description,
                'ar_small_description' => $request->ar_small_description ?? $product->ar_small_description,
                'en_small_description' => $request->en_small_description ?? $product->en_small_description,
                'sku' => $request->sku ?? $product->sku,
                'sale_price' => $request->sale_price ?? $product->sale_price ?? 0,
            ];

            $product->update($updateData);

            if ($request->hasFile('images')) {
                foreach ($product->images as $image) {
                    Storage::delete('public/media/'.$image->image);
                    $image->delete();
                }
                foreach ($request->file('images') as $image) {
                    $imageName = time().'_'.uniqid().'_'.$image->hashName();
                    $image->storeAs('public/media/', $imageName);
                    $product->images()->create([
                        'image' => $imageName,
                    ]);
                }
            }

            if ($request->has('features') && $request->features != null) {
                foreach ($product->features as $feature) {
                    $feature->delete();
                }
                foreach ($request->features as $feature) {
                    $product->features()->create([
                        'ar_title' => $feature['ar_title'],
                        'en_title' => $feature['en_title'],
                        'ar_description' => $feature['ar_description'],
                        'en_description' => $feature['en_description'],
                    ]);
                }
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'product' => $product->refresh()->load(['variants.color', 'variants.size', 'images', 'features']),
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

    public function destroy($product_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $product = Product::findOrFail($product_id);

        try {
            DB::beginTransaction();

            foreach ($product->images as $image) {
                Storage::delete('public/media/'.$image->image);
                $image->delete();
            }

            $product->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.product deleted successfully'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete product'),
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function changeStatus(Request $request, $product_id)
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

        $product = Product::findOrFail($product_id);

        try {
            DB::beginTransaction();
            $product->update([
                'status' => $request->status,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.product status changed successfully'),
                'product' => $product,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function deleteImage($image_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $image = Image::findOrFail($image_id);
        $product = $image->imageable()->first();
        if ($product->images()->count() == 1) {
            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete the only image of the product'),
            ], 400);
        }
        try {
            DB::beginTransaction();
            Storage::delete('public/media/'.$image->image);
            $image->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.image deleted successfully'),
                'product' => $product,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete image'),
            ], 400);
        }
    }

    public function addVariant(Request $request, $product_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $product = Product::findOrFail($product_id);

        $request->validate([
            'color_id' => 'required|exists:colors,id',
            'size_id' => 'required|exists:sizes,id',
            'quantity' => 'required|integer|min:0',
            'sale_price' => 'nullable|numeric|min:0',
        ]);

        $exists = ProductVariant::where('product_id', $product_id)
            ->where('color_id', $request->color_id)
            ->where('size_id', $request->size_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => __('responses.variant already exists'),
            ], 400);
        }

        try {
            DB::beginTransaction();

            $variant_sku = $product->sku.'-C'.$request->color_id.'-S'.$request->size_id;

            $variant = ProductVariant::create([
                'product_id' => $product_id,
                'color_id' => $request->color_id,
                'size_id' => $request->size_id,
                'quantity' => $request->quantity,
                'sale_price' => $request->sale_price ?? 0,
                'variant_sku' => $variant_sku,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.variant added successfully'),
                'variant' => $variant->load(['color', 'size']),
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

    public function updateVariant(Request $request, $variant_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $variant = ProductVariant::findOrFail($variant_id);

        $request->validate([
            'quantity' => 'sometimes|nullable|integer|min:0',
            'sale_price' => 'sometimes|nullable|numeric|min:0',
            'status' => 'sometimes|nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $variant->update([
                'quantity' => $request->quantity ?? $variant->quantity,
                'sale_price' => $request->sale_price ?? $variant->sale_price,
                'status' => $request->status ?? $variant->status,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.variant updated successfully'),
                'variant' => $variant->load(['color', 'size']),
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

    public function deleteVariant($variant_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-products')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $variant = ProductVariant::findOrFail($variant_id);

        try {
            DB::beginTransaction();
            $variant->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.variant deleted successfully'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete variant'),
            ], 400);
        }
    }
}
