<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SetCurrency
{
    public function handle(Request $request, Closure $next)
    {
        $currency = $request->header('X-Currency', 'SAR');
        if (Auth::guard('customers')->check()) {
            $customer = Auth::guard('customers')->user();
            $currency = $customer->currency;
        }
        session(['current_currency' => $currency]);

        return $next($request);
    }
}
