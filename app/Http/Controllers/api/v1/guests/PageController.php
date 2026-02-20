<?php

namespace App\Http\Controllers\api\v1\guests;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Setting;

class PageController extends Controller
{
    public function show()
    {
        $page = Page::firstOrFail();

        return response()->json([
            'success' => true,
            'message' => __('responses.page'),
            'page' => $page,
        ]);
    }

    public function settings()
    {
        $setting = Setting::firstOrFail();

        return response()->json([
            'success' => true,
            'message' => __('responses.setting'),
            'setting' => $setting,
        ]);
    }
}
