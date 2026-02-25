<?php

namespace App\Http\Controllers\api\v1\customers;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerAddressController extends Controller
{
    public function index()
    {
        $customer = Auth::guard('customers')->user();
        $addresses = $customer->addresses()->latest()->get();

        return response()->json([
            'success' => true,
            'message' => __('responses.all addresses'),
            'addresses' => $addresses,
        ]);
    }

    public function show($address_id)
    {
        $address = CustomerAddress::findOrFail($address_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.address'),
            'address' => $address,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'country_id' => 'required|exists:countries,id',
            'city_id' => 'required|exists:cities,id',
            'address_title' => 'required|string|max:255',
            'full_address' => 'required|string',
            'is_default' => 'required|boolean',
        ]);
        $customer = Auth::guard('customers')->user();
        $country = Country::where('status', true)->findOrFail($request->country_id);
        $city = City::where('status', true)->findOrFail($request->city_id);
        if ($city->country_id != $country->id) {
            return response()->json([
                'success' => false,
                'message' => __('responses.city does not belong to country'),
            ], 400);
        }
        try {
            DB::beginTransaction();
            $address = $customer->addresses()->create([
                'country_id' => $country->id,
                'city_id' => $city->id,
                'address_title' => $request->address_title,
                'full_address' => $request->full_address,
                'is_default' => $request->is_default,
            ]);
            if ($request->is_default) {
                $customer->addresses()->where('id', '!=', $address->id)->where('is_default', true)->update(['is_default' => false]);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.address created successfully'),
                'address' => $address,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function update(Request $request, $address_id)
    {
        $request->validate([
            'country_id' => 'sometimes|nullable|exists:countries,id',
            'city_id' => 'sometimes|nullable|exists:cities,id',
            'address_title' => 'sometimes|nullable|string|max:255',
            'full_address' => 'sometimes|nullable|string',
            'is_default' => 'sometimes|nullable|boolean',
        ]);
        $customer = Auth::guard('customers')->user();
        $address = $customer->addresses()->findOrFail($address_id);
        $country = Country::where('status', true)->findOrFail($request->country_id ?? $address->country_id);
        $city = City::where('status', true)->findOrFail($request->city_id ?? $address->city_id);
        if ($city->country_id != $country->id) {
            return response()->json([
                'success' => false,
                'message' => __('responses.city does not belong to country'),
            ], 400);
        }
        try {
            DB::beginTransaction();
            $address->update([
                'country_id' => $country->id ?? $address->country_id,
                'city_id' => $city->id ?? $address->city_id,
                'address_title' => $request->address_title ?? $address->address_title,
                'full_address' => $request->full_address ?? $address->full_address,
                'is_default' => $request->is_default ?? $address->is_default,
            ]);
            if ($request->is_default ?? $address->is_default) {
                $customer->addresses()->where('id', '!=', $address->id)->where('is_default', true)->update(['is_default' => false]);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.address updated successfully'),
                'address' => $address,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroy($address_id)
    {
        $address = CustomerAddress::findOrFail($address_id);
        $customer = Auth::guard('customers')->user();
        try {
            DB::beginTransaction();
            if ($address->is_default) {
                $customer->addresses()->where('id', '!=', $address->id)->where('is_default', false)->update(['is_default' => true]);
            }
            $address->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.address deleted successfully'),
                'address' => $address,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete address'),
            ], 400);
        }
    }

    public function setDefaultAddress($address_id)
    {
        $address = CustomerAddress::findOrFail($address_id);
        $customer = Auth::guard('customers')->user();
        try {
            DB::beginTransaction();
            $address->update(['is_default' => true]);
            $customer->addresses()->where('id', '!=', $address->id)->where('is_default', true)->update(['is_default' => false]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.address set as default successfully'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }
}
