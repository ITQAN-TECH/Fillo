<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // التحقق من وجود رأس 'Accept-Language' في الطلب
        // أو يمكنك استخدام أي رأس مخصص مثل 'X-Locale'
        $locale = $request->header('Accept-Language', 'ar');

        // إذا لم يتم العثور على رأس اللغة، يمكنك التحقق من مُعامل في URL
        if (! $locale) {
            $locale = $request->query('locale', 'ar');
        }

        // تعيين اللغة الافتراضية إذا لم يتم إرسال أي لغة
        if (! in_array($locale, ['ar', 'en'])) {
            $locale = config('app.locale', 'ar'); // أو 'en'
        }

        App::setLocale($locale);

        return $next($request);
    }
}
