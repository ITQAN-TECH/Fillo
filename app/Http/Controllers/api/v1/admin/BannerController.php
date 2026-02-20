<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-banners')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'search' => 'sometimes|nullable|string',
        ]);
        $banners = Banner::when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('ar_title', 'like', '%'.$search.'%')
                    ->orWhere('en_title', 'like', '%'.$search.'%');
            });
        })->latest()->paginate();
        $report = [];
        $report['banners_count'] = Banner::count();
        $report['active_banners_count'] = Banner::where('status', true)->count();
        $report['clicks_count'] = (int) Banner::sum('clicks');

        return response()->json([
            'success' => true,
            'message' => __('responses.all banners'),
            'report' => $report,
            'banners' => $banners,
        ]);
    }

    public function show($banner_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-banners')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $banner = Banner::findOrFail($banner_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.banner'),
            'banner' => $banner,
        ]);
    }

    public function store(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('create-banners')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'ar_title' => 'required|string',
            'en_title' => 'required|string',
            'url' => 'nullable|url',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:7168',
        ]);
        try {
            DB::beginTransaction();

            $banner = Banner::create([
                'ar_title' => $request->ar_title,
                'en_title' => $request->en_title,
                'url' => $request->url,
                'status' => true,
            ]);

            if ($request->hasFile('image')) {
                $name = $request->image->hashName();
                $filename = time().'_'.uniqid().'_'.$name;
                $request->image->storeAs('public/media/', $filename);
                $banner->update([
                    'image' => $filename,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'banner' => $banner,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function update(Request $request, $banner_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-banners')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'ar_title' => 'sometimes|nullable|string',
            'en_title' => 'sometimes|nullable|string',
            'url' => 'sometimes|nullable|url',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:7168',
        ]);
        $banner = Banner::findOrFail($banner_id);
        try {
            DB::beginTransaction();
            $banner->update([
                'ar_title' => $request->ar_title,
                'en_title' => $request->en_title,
                'url' => $request->url,
            ]);
            if ($request->hasFile('image') && $request->image != null) {
                $name = $request->image->hashName();
                $filename = time().'_'.uniqid().'_'.$name;
                $request->image->storeAs('public/media/', $filename);
                if ($banner->image) {
                    Storage::delete('public/media/'.$banner->image);
                }
                $banner->update([
                    'image' => $filename,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'banner' => $banner,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroy($banner_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-banners')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $banner = Banner::findOrFail($banner_id);
        try {
            DB::beginTransaction();

            $banner->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.banner deleted successfully'),
                'banner' => $banner,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete banner'),
            ], 400);
        }
    }

    public function changeStatus(Request $request, $banner_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-banners')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'status' => 'required|boolean',
        ]);
        $banner = Banner::findOrFail($banner_id);
        try {
            DB::beginTransaction();
            $banner->update([
                'status' => $request->status,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'banner' => $banner,
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
