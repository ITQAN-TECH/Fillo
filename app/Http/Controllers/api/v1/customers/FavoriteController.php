<?php

namespace App\Http\Controllers\api\v1\customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $user = Auth::guard('customers')->user();
        $favoritesQuery = Favorite::where('follower_id', $user->id)->with('following');
        $search = $request->input('search');
        if ($search) {
            $favoritesQuery->whereHas('following', function ($q) use ($search) {
                $searchTerm = '%'.$search.'%';
                $q->where('name', 'like', $searchTerm);
            });
        }
        $favorites = $favoritesQuery->latest()->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('responses.Favorites for user'),
            'Favorites' => $favorites,
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'following_id' => 'required|exists:customers,id',
        ]);
        $following = Customer::findOrFail($request->following_id);
        $customer = Auth::guard('customers')->user();
        try {
            if ($following->id == $customer->id) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.You cannot favorite your own profile'),
                ], 400);
            }
            if (Favorite::where('follower_id', $customer->id)->where('following_id', $request->following_id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.This Customer is already in your Favorites'),
                ], 400);
            }
            $favorite = Favorite::create([
                'following_id' => $request->following_id,
                'follower_id' => $customer->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('responses.Customer added to Favorite successfully!.'),
                'favorite' => $favorite,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroy($following_id)
    {
        $favorite = Favorite::where('follower_id', Auth::guard('customers')->id())->where('following_id', $following_id)->firstOrFail();
        try {
            $favorite->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.Customer removed from Favorites successfully!.'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function empty()
    {
        try {
            Favorite::where('follower_id', Auth::guard('customers')->id())->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.Favorites deleted successfully'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }
}
