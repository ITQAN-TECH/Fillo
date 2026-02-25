<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-customers')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'search' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|in:active,inactive,all',
        ]);
        $customers = Customer::when($request->has('status') && $request->status != 'all', function ($query) use ($request) {
            $query->where('status', $request->status == 'active' ? true : false);
        })
            ->when($request->has('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('id', $search);
                });
            })->with('addresses')->latest()->paginate();
        $report = [];
        $report['customers_count'] = Customer::count();
        $report['active_customers_count'] = Customer::where('status', true)->count();
        $report['inactive_customers_count'] = Customer::where('status', false)->count();

        return response()->json([
            'success' => true,
            'message' => __('responses.all customers'),
            'report' => $report,
            'customers' => $customers,
        ]);
    }

    public function search(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-customers')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'search' => 'sometimes|nullable|string',
        ]);
        $customers = Customer::when($request->has('search'), function ($query) use ($request) {
            $query->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('email', 'like', '%'.$request->search.'%')
                ->orWhere('phone', 'like', '%'.$request->search.'%')
                ->orWhere('id', $request->search);
        })->latest()->limit(10)->get();

        return response()->json([
            'success' => true,
            'message' => __('responses.all customers'),
            'customers' => $customers,
        ]);
    }

    public function show($customer_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-customers')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $customer = Customer::with('addresses')->findOrFail($customer_id);
        $rates = $customer->rates()->with('rateable')->latest()->paginate();

        return response()->json([
            'success' => true,
            'message' => __('responses.customer'),
            'customer' => $customer,
            'rates' => $rates,
        ]);
    }

    public function destroy($customer_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-customers')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $customer = Customer::findOrFail($customer_id);
        try {
            $customer->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.customer deleted successfully'),
                'customer' => $customer,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete customer'),
            ], 400);
        }
    }

    public function changeStatus(Request $request, $customer_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-customers')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'status' => 'required|boolean',
        ]);
        $customer = Customer::findOrFail($customer_id);
        $customer->update([
            'status' => $request->status,
        ]);

        return response()->noContent();
    }
}
