<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CityController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-cities')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'search' => 'sometimes|nullable|string',
        ]);
        $cities = City::when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('ar_name', 'like', '%'.$search.'%')
                    ->orWhere('en_name', 'like', '%'.$search.'%');
            });
        })->with('country')->latest()->paginate();
        $report = [];
        $report['cities_count'] = City::count();
        $report['active_cities_count'] = City::where('status', true)->count();
        $report['inactive_cities_count'] = City::where('status', false)->count();

        return response()->json([
            'success' => true,
            'message' => __('responses.all cities'),
            'report' => $report,
            'cities' => $cities,
        ]);
    }

    public function show($city_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-cities')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $city = City::with('country')->findOrFail($city_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.city'),
            'city' => $city,
        ]);
    }

    public function store(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('create-cities')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'country_id' => 'required|exists:countries,id',
            'ar_name' => 'required|string|max:255',
            'en_name' => 'required|string|max:255',
        ]);
        try {
            DB::beginTransaction();
            $city = City::create([
                'country_id' => $request->country_id,
                'ar_name' => $request->ar_name,
                'en_name' => $request->en_name,
                'status' => true,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'city' => $city->refresh(),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function update(Request $request, $city_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-cities')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'country_id' => 'sometimes|nullable|exists:countries,id',
            'ar_name' => 'sometimes|nullable|string|max:255',
            'en_name' => 'sometimes|nullable|string|max:255',
        ]);
        $city = City::with('country')->findOrFail($city_id);
        try {
            DB::beginTransaction();
            $city->update([
                'country_id' => $request->country_id ?? $city->country_id,
                'ar_name' => $request->ar_name ?? $city->ar_name,
                'en_name' => $request->en_name ?? $city->en_name,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'city' => $city->refresh(),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroy($city_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-cities')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $city = City::with('country')->findOrFail($city_id);
        try {
            $city->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.city deleted successfully'),
                'city' => $city->refresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete city'),
            ], 400);
        }
    }

    public function changeStatus(Request $request, $city_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-cities')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'status' => 'required|boolean',
        ]);
        $city = City::with('country')->findOrFail($city_id);
        try {
            DB::beginTransaction();
            $city->update([
                'status' => $request->status,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'city' => $city->refresh(),
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
