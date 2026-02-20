<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PageController extends Controller
{
    public function show()
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-pages')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $page = Page::firstOrFail();

        return response()->json([
            'success' => true,
            'message' => __('responses.page'),
            'page' => $page,
        ]);
    }

    public function update(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-pages')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'ar_about_us' => 'sometimes|nullable|string',
            'en_about_us' => 'sometimes|nullable|string',
            'ar_terms_and_conditions' => 'sometimes|nullable|string',
            'en_terms_and_conditions' => 'sometimes|nullable|string',
            'ar_privacy_policy' => 'sometimes|nullable|string',
            'en_privacy_policy' => 'sometimes|nullable|string',
        ]);
        $page = Page::firstOrFail();
        try {
            DB::beginTransaction();
            $page->update([
                'ar_about_us' => $request->ar_about_us ?? $page->ar_about_us,
                'en_about_us' => $request->en_about_us ?? $page->en_about_us,
                'ar_terms_and_conditions' => $request->ar_terms_and_conditions ?? $page->ar_terms_and_conditions,
                'en_terms_and_conditions' => $request->en_terms_and_conditions ?? $page->en_terms_and_conditions,
                'ar_privacy_policy' => $request->ar_privacy_policy ?? $page->ar_privacy_policy,
                'en_privacy_policy' => $request->en_privacy_policy ?? $page->en_privacy_policy,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'page' => $page,
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
