<?php

namespace App\Http\Controllers\api\v1\guests;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'country_id' => 'sometimes|nullable|exists:countries,id',
        ]);
        $cities = City::when($request->has('country_id'), function ($query) use ($request) {
            $query->where('country_id', $request->country_id);
        })->with('country')->where('status', true)->latest()->get();

        return response()->json([
            'success' => true,
            'message' => __('responses.all cities'),
            'cities' => $cities,
        ]);
    }

    public function show($city_id)
    {
        $city = City::with('country')->where('status', true)->findOrFail($city_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.city'),
            'city' => $city,
        ]);
    }
}
