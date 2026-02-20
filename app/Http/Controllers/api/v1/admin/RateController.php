<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Rate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RateController extends Controller
{
    public function destroy($rate_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-rates')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $rate = Rate::findOrFail($rate_id);
        try {
            DB::beginTransaction();
            $rate->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.rate deleted successfully'),
                'rate' => $rate,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete rate'),
            ], 400);
        }
    }
}
