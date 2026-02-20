<?php

namespace App\Http\Controllers\api\v1\guests;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'sometimes|nullable|string',
            'is_featured' => 'sometimes|nullable|boolean',
            'per_page' => 'sometimes|nullable|integer|min:1|max:100',
        ]);
        $services = Service::when($request->has('is_featured'), function ($query) use ($request) {
            $query->where('is_featured', $request->is_featured);
        })->when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where('ar_name', 'like', '%' . $search . '%')
                ->orWhere('en_name', 'like', '%' . $search . '%')
                ->orWhere('ar_description', 'like', '%' . $search . '%')
                ->orWhere('en_description', 'like', '%' . $search . '%');
        })->where('status', true)->latest()->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'message' => __('responses.all services'),
            'services' => $services,
        ]);
    }

    public function show($service_id)
    {
        $service = Service::where('status', true)->findOrFail($service_id);
        $rates = $service->rates()->with('customer')->latest()->paginate();

        $starCounts = [];
        $starPercentages = [];
        $totalRates = $service->rates()->count();
        for ($i = 1; $i <= 5; $i++) {
            $count = $service->rates()->where('rate', $i)->count();
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
        return response()->json([
            'success' => true,
            'message' => __('responses.service'),
            'rate_stats' => $rate_stats,
            'service' => $service,
            'rates' => $rates,
        ]);
    }
}
