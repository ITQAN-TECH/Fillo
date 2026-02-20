<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CountryController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-countries')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'search' => 'sometimes|nullable|string',
        ]);
        $countries = Country::when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('ar_name', 'like', '%'.$search.'%')
                    ->orWhere('en_name', 'like', '%'.$search.'%');
            });
        })->latest()->paginate();
        $report = [];
        $report['countries_count'] = Country::count();
        $report['active_countries_count'] = Country::where('status', true)->count();
        $report['inactive_countries_count'] = Country::where('status', false)->count();

        return response()->json([
            'success' => true,
            'message' => __('responses.all countries'),
            'report' => $report,
            'countries' => $countries,
        ]);
    }

    public function show($country_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-countries')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $country = Country::findOrFail($country_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.country'),
            'country' => $country,
        ]);
    }

    public function store(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('create-countries')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'ar_name' => 'required|string|max:255',
            'en_name' => 'required|string|max:255',
            'flag' => 'sometimes|nullable|file|mimes:jpg,jpeg,png,gif,svg,webp|max:7096',
        ]);
        try {
            DB::beginTransaction();
            $country = Country::create([
                'ar_name' => $request->ar_name,
                'en_name' => $request->en_name,
                'status' => true,
            ]);
            if ($request->hasFile('flag')) {
                $name = $request->flag->hashName();
                $filename = time() . '_' . uniqid() . '_' . $name;
                $request->flag->storeAs('public/media/', $filename);
                $country->update([
                    'flag' => $filename,
                ]);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'country' => $country,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function update(Request $request, $country_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-countries')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'ar_name' => 'sometimes|nullable|string|max:255',
            'en_name' => 'sometimes|nullable|string|max:255',
            'flag' => 'sometimes|nullable|file|mimes:jpg,jpeg,png,gif,svg,webp|max:7096',
        ]);
        $country = Country::findOrFail($country_id);
        try {
            DB::beginTransaction();
            $country->update([
                'ar_name' => $request->ar_name ?? $country->ar_name,
                'en_name' => $request->en_name ?? $country->en_name,
            ]);
            if ($request->hasFile('flag')) {
                Storage::delete('public/media/' . $country->flag);
                $name = $request->flag->hashName();
                $filename = time() . '_' . uniqid() . '_' . $name;
                $request->flag->storeAs('public/media/', $filename);
                $country->update([
                    'flag' => $filename,
                ]);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'country' => $country,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroy($country_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-countries')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $country = Country::findOrFail($country_id);
        try {
            $country->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.country deleted successfully'),
                'country' => $country,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete country'),
            ], 400);
        }
    }

    public function changeStatus(Request $request, $country_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-countries')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'status' => 'required|boolean',
        ]);
        $country = Country::findOrFail($country_id);
        try {
            DB::beginTransaction();
            $country->update([
                'status' => $request->status,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'country' => $country,
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
