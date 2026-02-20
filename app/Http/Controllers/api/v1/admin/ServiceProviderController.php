<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use App\Models\Country;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ServiceProviderController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-service-providers')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'search' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|in:active,inactive,all',
        ]);
        $serviceProviders = ServiceProvider::with('citiesOfWorking')
            ->when($request->has('status') && $request->status != 'all', function ($query) use ($request) {
                $query->where('status', $request->status == 'active' ? true : false);
            })
            ->when($request->has('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('store_name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('full_address', 'like', '%' . $search . '%')
                        ->orWhere('specialization', 'like', '%' . $search . '%');
                });
            })->latest()->paginate();
        $report = [];
        $report['service_providers_count'] = ServiceProvider::count();
        $report['active_service_providers_count'] = ServiceProvider::where('status', true)->count();
        $report['inactive_service_providers_count'] = ServiceProvider::where('status', false)->count();

        return response()->json([
            'success' => true,
            'message' => __('responses.all service providers'),
            'report' => $report,
            'service_providers' => $serviceProviders,
        ]);
    }

    public function show($service_provider_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-service-providers')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $serviceProvider = ServiceProvider::with('citiesOfWorking')->findOrFail($service_provider_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.service provider'),
            'service_provider' => $serviceProvider,
        ]);
    }

    public function store(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('create-service-providers')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'name' => 'required|string|max:255',
            'store_name' => 'sometimes|nullable|string|max:255',
            'type' => 'required|in:individual,company,store',
            'phone' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'full_address' => 'required|string|max:255',
            'image' => 'sometimes|nullable|file|mimes:jpg,jpeg,png,webp|max:7096',
            'specialization' => 'sometimes|nullable|string|max:255',
            'working_hours_start' => 'required|date_format:H:i:s',
            'working_hours_end' => 'required|date_format:H:i:s',
            'daily_orders_count' => 'required|integer|min:1',
            'id_file' => 'sometimes|nullable|file|mimes:pdf,jpg,jpeg,png,pdf,webp|max:7096',
            'commercial_id_file' => 'sometimes|nullable|file|mimes:pdf,jpg,jpeg,png,pdf,webp|max:7096',
            'service_practice_certificate_file' => 'sometimes|nullable|file|mimes:pdf,jpg,jpeg,png,pdf,webp|max:7096',
            'country_id' => 'required|exists:countries,id',
            'city_id' => 'required|exists:cities,id',
            'cities_of_working' => 'required|array',
            'cities_of_working.*' => 'required|exists:cities,id',
        ]);
        $country = Country::where('status', true)->findOrFail($request->country_id);
        $city = City::where('status', true)->findOrFail($request->city_id);
        if ($country->id != $city->country_id) {
            return response()->json([
                'success' => false,
                'message' => __('responses.country and city do not match'),
            ], 400);
        }
        try {
            DB::beginTransaction();
            $serviceProvider = ServiceProvider::create([
                'name' => $request->name,
                'store_name' => $request->store_name,
                'type' => $request->type,
                'phone' => $request->phone,
                'email' => $request->email,
                'full_address' => $request->full_address,
                'specialization' => $request->specialization,
                'working_hours_start' => $request->working_hours_start,
                'working_hours_end' => $request->working_hours_end,
                'daily_orders_count' => $request->daily_orders_count,
                'id_file' => $request->id_file,
                'commercial_id_file' => $request->commercial_id_file,
                'service_practice_certificate_file' => $request->service_practice_certificate_file,
                'status' => true,
                'country_id' => $request->country_id,
                'city_id' => $request->city_id,
            ]);
            $serviceProvider->citiesOfWorking()->sync($request->cities_of_working);
            if ($request->hasFile('image')) {
                $name = $request->image->hashName();
                $filename = time() . '_' . uniqid() . '_' . $name;
                $request->image->storeAs('public/media/', $filename);
                $serviceProvider->update([
                    'image' => $filename,
                ]);
            }
            if ($request->hasFile('id_file')) {
                Storage::delete('public/media/' . $serviceProvider->id_file);
                $name = $request->id_file->hashName();
                $filename = time() . '_' . uniqid() . '_' . $name;
                $request->id_file->storeAs('public/media/', $filename);
                $serviceProvider->update([
                    'id_file' => $filename,
                ]);
            }
            if ($request->hasFile('commercial_id_file')) {
                Storage::delete('public/media/' . $serviceProvider->commercial_id_file);
                $name = $request->commercial_id_file->hashName();
                $filename = time() . '_' . uniqid() . '_' . $name;
                $request->commercial_id_file->storeAs('public/media/', $filename);
                $serviceProvider->update([
                    'commercial_id_file' => $filename,
                ]);
            }
            if ($request->hasFile('service_practice_certificate_file')) {
                Storage::delete('public/media/' . $serviceProvider->service_practice_certificate_file);
                $name = $request->service_practice_certificate_file->hashName();
                $filename = time() . '_' . uniqid() . '_' . $name;
                $request->service_practice_certificate_file->storeAs('public/media/', $filename);
                $serviceProvider->update([
                    'service_practice_certificate_file' => $filename,
                ]);
            }
            $serviceProvider->load('citiesOfWorking');
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'service_provider' => $serviceProvider,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function update(Request $request, $service_provider_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-service-providers')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'store_name' => 'sometimes|nullable|string|max:255',
            'type' => 'sometimes|nullable|in:individual,company,store',
            'phone' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'image' => 'sometimes|nullable|file|mimes:jpg,jpeg,png|max:7096',
            'full_address' => 'sometimes|nullable|string|max:255',
            'specialization' => 'sometimes|nullable|string|max:255',
            'working_hours_start' => 'sometimes|nullable|date_format:H:i:s',
            'working_hours_end' => 'sometimes|nullable|date_format:H:i:s',
            'daily_orders_count' => 'sometimes|nullable|integer|min:1',
            'id_file' => 'sometimes|nullable|file|mimes:pdf,jpg,jpeg,png,pdf,webp|max:7096',
            'commercial_id_file' => 'sometimes|nullable|file|mimes:pdf,jpg,jpeg,png,pdf,webp|max:7096',
            'service_practice_certificate_file' => 'sometimes|nullable|file|mimes:pdf,jpg,jpeg,png,pdf,webp|max:7096',
            'country_id' => 'sometimes|nullable|exists:countries,id',
            'city_id' => 'sometimes|nullable|exists:cities,id',
            'cities_of_working' => 'sometimes|nullable|array',
            'cities_of_working.*' => 'sometimes|nullable|exists:cities,id',
        ]);
        $serviceProvider = ServiceProvider::findOrFail($service_provider_id);
        $country = Country::where('status', true)->findOrFail($request->country_id ?? $serviceProvider->country_id);
        $city = City::where('status', true)->findOrFail($request->city_id ?? $serviceProvider->city_id);
        if ($country->id != $city->country_id) {
            return response()->json([
                'success' => false,
                'message' => __('responses.country and city do not match'),
            ], 400);
        }
        try {
            DB::beginTransaction();
            $serviceProvider->update([
                'name' => $request->name ?? $serviceProvider->name,
                'store_name' => $request->store_name ?? $serviceProvider->store_name,
                'type' => $request->type ?? $serviceProvider->type,
                'phone' => $request->phone ?? $serviceProvider->phone,
                'email' => $request->email ?? $serviceProvider->email,
                'full_address' => $request->full_address ?? $serviceProvider->full_address,
                'specialization' => $request->specialization ?? $serviceProvider->specialization,
                'working_hours_start' => $request->working_hours_start ?? $serviceProvider->working_hours_start,
                'working_hours_end' => $request->working_hours_end ?? $serviceProvider->working_hours_end,
                'daily_orders_count' => $request->daily_orders_count ?? $serviceProvider->daily_orders_count,
                'country_id' => $request->country_id ?? $serviceProvider->country_id,
                'city_id' => $request->city_id ?? $serviceProvider->city_id,
            ]);
            if ($request->has('cities_of_working')) {
                $serviceProvider->citiesOfWorking()->sync($request->cities_of_working);
            } else {
                $serviceProvider->citiesOfWorking()->sync([]);
            }
            if ($request->has('image')) {
                Storage::delete('public/media/' . $serviceProvider->image);
                $name = $request->image->hashName();
                $filename = time() . '_' . uniqid() . '_' . $name;
                $request->image->storeAs('public/media/', $filename);
                $serviceProvider->update([
                    'image' => $filename,
                ]);
            }
            if ($request->has('id_file')) {
                Storage::delete('public/media/' . $serviceProvider->id_file);
                $name = $request->id_file->hashName();
                $filename = time() . '_' . uniqid() . '_' . $name;
                $request->id_file->storeAs('public/media/', $filename);
                $serviceProvider->update([
                    'id_file' => $filename,
                ]);
            }
            if ($request->has('commercial_id_file')) {
                Storage::delete('public/media/' . $serviceProvider->commercial_id_file);
                $name = $request->commercial_id_file->hashName();
                $filename = time() . '_' . uniqid() . '_' . $name;
                $request->commercial_id_file->storeAs('public/media/', $filename);
                $serviceProvider->update([
                    'commercial_id_file' => $filename,
                ]);
            }
            if ($request->has('service_practice_certificate_file')) {
                Storage::delete('public/media/' . $serviceProvider->service_practice_certificate_file);
                $name = $request->service_practice_certificate_file->hashName();
                $filename = time() . '_' . uniqid() . '_' . $name;
                $request->service_practice_certificate_file->storeAs('public/media/', $filename);
                $serviceProvider->update([
                    'service_practice_certificate_file' => $filename,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'service_provider' => $serviceProvider->refresh(),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroy($service_provider_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-service-providers')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $serviceProvider = ServiceProvider::findOrFail($service_provider_id);
        try {
            $serviceProvider->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.service provider deleted successfully'),
                'service_provider' => $serviceProvider,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete service provider'),
            ], 400);
        }
    }

    public function changeStatus(Request $request, $service_provider_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-service-providers')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'status' => 'required|boolean',
        ]);
        $serviceProvider = ServiceProvider::findOrFail($service_provider_id);
        try {
            DB::beginTransaction();
            $serviceProvider->update([
                'status' => $request->status,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'service_provider' => $serviceProvider,
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
