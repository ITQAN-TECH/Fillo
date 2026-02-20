<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\RateLimiter;

abstract class Controller
{
    public function throttle($ip, $name, $attempts, $minutes = 1)
    {
        $minuteKey = "{$name}:".$ip;
        if (! RateLimiter::attempt($minuteKey, $attempts, function () {
            // No action needed if within the limit
        }, $minutes * 60)) {
            return response()->json([
                'status' => false,
                'message' => __('responses.Too Many Requests.'),
                'seconds' => RateLimiter::availableIn($minuteKey),
            ], 429);
        }

        return true;
    }
}
