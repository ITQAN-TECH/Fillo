<?php

namespace App\Http\Controllers\api\v1\guests;

use App\Http\Controllers\Controller;
use App\Models\Banner;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::where('status', true)->latest()->get();

        return response()->json([
            'success' => true,
            'message' => __('responses.all banners'),
            'banners' => $banners,
        ]);
    }

    public function show($banner_id)
    {
        $banner = Banner::where('status', true)->findOrFail($banner_id);
        $banner->increment('clicks');

        return response()->json([
            'success' => true,
            'message' => __('responses.banner'),
            'banner' => $banner,
        ]);
    }
}
