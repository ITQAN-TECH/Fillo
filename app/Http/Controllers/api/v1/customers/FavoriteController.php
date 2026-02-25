<?php

namespace App\Http\Controllers\api\v1\customers;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => 'nullable|in:service,product,all',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $user = Auth::guard('customers')->user();
        $favorites = Favorite::where('customer_id', $user->id)->with('service', 'product')
            ->when($request->has('type') && $request->type == 'service', function ($query) {
                $query->whereHas('service', function ($q) {
                    $q->where('status', true);
                });
            })->when($request->has('type') && $request->type == 'product', function ($query) {
                $query->whereHas('product', function ($q) {
                    $q->where('status', true);
                });
            })
            ->when(! $request->has('type') || $request->type == 'all', function ($query) {
                $query->where(function ($query) {
                    $query->whereHas('service', function ($q) {
                        $q->where('status', true);
                    });
                    $query->orWhereHas('product', function ($q) {
                        $q->where('status', true);
                    });
                });
            })
            ->when($request->has('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->whereHas('service', function ($q) use ($search) {
                    $q->where('ar_name', 'like', '%'.$search.'%')
                        ->orWhere('en_name', 'like', '%'.$search.'%')
                        ->orWhere('ar_description', 'like', '%'.$search.'%')
                        ->orWhere('en_description', 'like', '%'.$search.'%');
                });
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('ar_name', 'like', '%'.$search.'%')
                        ->orWhere('en_name', 'like', '%'.$search.'%')
                        ->orWhere('ar_description', 'like', '%'.$search.'%')
                        ->orWhere('en_description', 'like', '%'.$search.'%')
                        ->orWhere('ar_short_description', 'like', '%'.$search.'%')
                        ->orWhere('en_short_description', 'like', '%'.$search.'%');
                });
            })
            ->latest()->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'message' => __('responses.Favorites for user'),
            'Favorites' => $favorites,
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'service_id' => ['nullable', 'exists:services,id', 'required_without:product_id', 'prohibits:product_id'],
            'product_id' => ['nullable', 'exists:products,id', 'required_without:service_id', 'prohibits:service_id'],
        ]);
        $customer = Auth::guard('customers')->user();
        if ($request->service_id) {
            $service = Service::where('status', true)->findOrFail($request->service_id);
        }
        if ($request->product_id) {
            $product = Product::where('status', true)->findOrFail($request->product_id);
        }
        try {
            Favorite::updateOrCreate([
                'customer_id' => $customer->id,
                'service_id' => $request->service_id,
                'product_id' => $request->product_id,
            ], [
                'customer_id' => $customer->id,
                'service_id' => $request->service_id,
                'product_id' => $request->product_id,
            ]);
            if ($request->service_id) {
                return response()->json([
                    'success' => true,
                    'message' => __('responses.Service added to Favorite successfully!.'),
                ], 201);
            }
            if ($request->product_id) {
                return response()->json([
                    'success' => true,
                    'message' => __('responses.Product added to Favorite successfully!.'),
                ], 201);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroyService($service_id)
    {
        $favorite = Favorite::where('customer_id', Auth::guard('customers')->id())->where('service_id', $service_id)->firstOrFail();
        try {
            $favorite->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.Service removed from Favorites successfully!.'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroyProduct($product_id)
    {
        $favorite = Favorite::where('customer_id', Auth::guard('customers')->id())->where('product_id', $product_id)->firstOrFail();
        try {
            $favorite->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.Product removed from Favorites successfully!.'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function empty(Request $request)
    {
        $request->validate([
            'type' => 'required|in:service,product,all',
        ]);
        try {
            if ($request->type == 'service') {
                Favorite::where('customer_id', Auth::guard('customers')->id())->whereNotNull('service_id')->delete();
            }
            if ($request->type == 'product') {
                Favorite::where('customer_id', Auth::guard('customers')->id())->whereNotNull('product_id')->delete();
            }
            if ($request->type == 'all') {
                Favorite::where('customer_id', Auth::guard('customers')->id())->delete();
            }

            return response()->json([
                'success' => true,
                'message' => __('responses.favorites deleted successfully'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }
}
