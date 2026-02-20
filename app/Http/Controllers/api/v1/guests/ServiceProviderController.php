<?php

namespace App\Http\Controllers\api\v1\guests;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;

class ServiceProviderController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'sometimes|nullable|string',
            'per_page' => 'sometimes|nullable|integer|min:1|max:100',
        ]);
        $serviceProviders = ServiceProvider::when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where('name', 'like', '%' . $search . '%')
                ->orWhere('store_name', 'like', '%' . $search . '%')
                ->orWhere('phone', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%')
                ->orWhere('full_address', 'like', '%' . $search . '%')
                ->orWhere('specialization', 'like', '%' . $search . '%');
        })->where('status', true)->withCount('services')->latest()->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'message' => __('responses.all service providers'),
            'serviceProviders' => $serviceProviders,
        ]);
    }

    public function show($serviceProvider_id)
    {
        $serviceProvider = ServiceProvider::where('status', true)->findOrFail($serviceProvider_id);
        $services = $serviceProvider->services()->latest()->paginate();
        return response()->json([
            'success' => true,
            'message' => __('responses.service provider'),
            'serviceProvider' => $serviceProvider,
            'services' => $services,
        ]);
    }
}
