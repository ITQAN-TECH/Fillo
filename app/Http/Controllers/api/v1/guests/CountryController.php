<?php

namespace App\Http\Controllers\api\v1\guests;

use App\Http\Controllers\Controller;
use App\Models\Country;

class CountryController extends Controller
{
    public function index()
    {
        $countries = Country::where('status', true)->latest()->get();

        return response()->json([
            'success' => true,
            'message' => __('responses.all countries'),
            'countries' => $countries,
        ]);
    }

    public function show($country_id)
    {
        $country = Country::where('status', true)->findOrFail($country_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.country'),
            'country' => $country,
        ]);
    }
}
