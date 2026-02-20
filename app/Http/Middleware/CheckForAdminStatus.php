<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckForAdminStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('admins')->check()) {
            if (! Auth::guard('admins')->user()->status) {
                Auth::guard('admins')->user()->currentAccessToken()->delete();

                return response()->json([
                    'success' => false,
                    'message' => __('responses.This Admin is banned'),
                ], 400);
            } else {
                return $next($request);
            }
        } else {
            return $next($request);
        }
    }
}
