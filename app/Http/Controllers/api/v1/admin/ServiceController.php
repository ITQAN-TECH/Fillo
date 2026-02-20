<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use App\Models\SubCategory;
use App\Models\ServiceProvider;
use App\Models\Image;
use App\Models\Rate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-services')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'search' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|in:active,inactive,all',
        ]);
        $services = Service::when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('ar_name', 'like', '%' . $search . '%')
                    ->orWhere('en_name', 'like', '%' . $search . '%')
                    ->orWhere('ar_description', 'like', '%' . $search . '%')
                    ->orWhere('en_description', 'like', '%' . $search . '%');
            });
        })->when($request->has('status') && $request->status != 'all', function ($query) use ($request) {
            $query->where('status', $request->status == 'active' ? true : false);
        })->latest()->paginate();
        $report = [];
        $report['services_count'] = Service::count();
        $report['active_services_count'] = Service::where('status', true)->count();
        $report['inactive_services_count'] = Service::where('status', false)->count();
        $report['featured_services_count'] = Service::where('is_featured', true)->count();

        return response()->json([
            'success' => true,
            'message' => __('responses.all services'),
            'report' => $report,
            'services' => $services,
        ]);
    }

    public function show($service_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-services')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $service = Service::findOrFail($service_id);
        $rates = $service->rates()->with('customer')->latest()->paginate();
        return response()->json([
            'success' => true,
            'message' => __('responses.service'),
            'service' => $service,
            'rates' => $rates,
        ]);
    }

    public function store(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('create-services')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'ar_name' => 'required|string',
            'en_name' => 'required|string',
            'ar_description' => 'required|string',
            'en_description' => 'required|string',
            'service_provider_price' => 'required|numeric|gt:0',
            'sale_price' => 'required|numeric|gte:service_provider_price',
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'required|exists:sub_categories,id',
            'service_provider_id' => 'required|exists:service_providers,id',
            'images' => 'required|array',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:7168',
        ]);
        $category = Category::findOrFail($request->category_id);
        $subCategory = SubCategory::findOrFail($request->sub_category_id);
        if ($subCategory->category_id != $category->id) {
            return response()->json([
                'success' => false,
                'message' => __('responses.sub category does not belong to category'),
            ], 400);
        }
        $serviceProvider = ServiceProvider::findOrFail($request->service_provider_id);
        try {
            DB::beginTransaction();

            $service = Service::create([
                'ar_name' => $request->ar_name,
                'en_name' => $request->en_name,
                'ar_description' => $request->ar_description,
                'en_description' => $request->en_description,
                'service_provider_price' => $request->service_provider_price,
                'sale_price' => $request->sale_price,
                'profit_amount' => $request->sale_price - $request->service_provider_price,
                'category_id' => $request->category_id,
                'sub_category_id' => $request->sub_category_id,
                'service_provider_id' => $request->service_provider_id,
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $name = $image->hashName();
                    $filename = time() . '_' . uniqid() . '_' . $name;
                    $image->storeAs('public/media/', $filename);
                    $service->images()->create([
                        'image' => $filename,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.service created successfully'),
                'service' => $service->load('images', 'category', 'subCategory', 'serviceProvider'),
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

    public function update(Request $request, $service_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-services')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'ar_name' => 'sometimes|nullable|string',
            'en_name' => 'sometimes|nullable|string',
            'ar_description' => 'sometimes|nullable|string',
            'en_description' => 'sometimes|nullable|string',
            'service_provider_price' => 'sometimes|nullable|numeric|gt:0',
            'sale_price' => 'sometimes|nullable|numeric|gte:service_provider_price',
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'sub_category_id' => 'sometimes|nullable|exists:sub_categories,id',
            'service_provider_id' => 'sometimes|nullable|exists:service_providers,id',
            'images' => 'sometimes|nullable|array',
            'images.*' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:7168',
        ]);
        $service = Service::findOrFail($service_id);
        $category = Category::findOrFail($request->category_id ?? $service->category_id);
        $subCategory = SubCategory::findOrFail($request->sub_category_id ?? $service->sub_category_id);
        if ($subCategory->category_id != $category->id) {
            return response()->json([
                'success' => false,
                'message' => __('responses.sub category does not belong to category'),
            ], 400);
        }
        try {
            DB::beginTransaction();
            $service->update([
                'ar_name' => $request->ar_name ?? $service->ar_name,
                'en_name' => $request->en_name ?? $service->en_name,
                'ar_description' => $request->ar_description ?? $service->ar_description,
                'en_description' => $request->en_description ?? $service->en_description,
                'service_provider_price' => $request->service_provider_price ?? $service->service_provider_price,
                'sale_price' => $request->sale_price ?? $service->sale_price,
                'profit_amount' => $request->sale_price ?? $service->sale_price - $request->service_provider_price ?? $service->profit_amount,
                'category_id' => $request->category_id ?? $service->category_id,
                'sub_category_id' => $request->sub_category_id ?? $service->sub_category_id,
                'service_provider_id' => $request->service_provider_id ?? $service->service_provider_id,
            ]);
            if ($request->hasFile('images') && $request->images != null) {
                foreach ($service->images as $image) {
                    Storage::delete('public/media/' . $image->image);
                    $image->delete();
                }
                foreach ($request->file('images') as $image) {
                    $name = $image->hashName();
                    $filename = time() . '_' . uniqid() . '_' . $name;
                    $image->storeAs('public/media/', $filename);
                    $service->images()->create([
                        'image' => $filename,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'service' => $service->refresh(),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroy($service_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-services')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $service = Service::findOrFail($service_id);
        try {
            DB::beginTransaction();
            foreach ($service->images as $image) {
                Storage::delete('public/media/' . $image->image);
                $image->delete();
            }
            $service->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.service deleted successfully'),
                'service' => $service,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete service'),
            ], 400);
        }
    }

    public function changeFeatured(Request $request, $service_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-services')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'is_featured' => 'required|boolean',
        ]);
        $service = Service::findOrFail($service_id);
        try {
            DB::beginTransaction();
            $service->update([
                'is_featured' => $request->is_featured,
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'service' => $service,
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
        if (! Auth::guard('admins')->user()->hasPermission('edit-services')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $image = Image::findOrFail($image_id);
        $service = $image->imageable()->first();
        if ($service->images()->count() == 1) {
            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete the only image of the service'),
            ], 400);
        }
        try {
            DB::beginTransaction();
            Storage::delete('public/media/' . $image->image);
            $image->delete();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => __('responses.image deleted successfully'),
                'service' => $service,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete image'),
            ], 400);
        }
    }

    public function changeStatus(Request $request, $service_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-services')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'status' => 'required|boolean',
        ]);
        $service = Service::findOrFail($service_id);
        try {
            DB::beginTransaction();
            $service->update([
                'status' => $request->status,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.service status changed successfully'),
                'service' => $service,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot change service status'),
            ], 400);
        }
    }
}
