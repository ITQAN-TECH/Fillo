<?php

namespace App\Http\Controllers\api\v1\customers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function editProfile(Request $request)
    {
        $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:7168'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore(Auth::guard('customers')->id())],
            'national_address_short_number' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
        $customer = Auth::guard('customers')->user();
        try {
            DB::beginTransaction();
            $customer->update([
                'name' => $request->name ?? $customer->name,
                'email' => $request->email ?? $customer->email,
                'national_address_short_number' => $request->national_address_short_number ?? $customer->national_address_short_number,
            ]);
            if ($request->hasFile('image')) {
                if ($customer->image) {
                    Storage::delete('public/media/'.$customer->image);
                }
                $name = $request->image->hashName();
                $filename = time().'_'.uniqid().'_'.$name;
                $request->image->storeAs('public/media/', $filename);
                $customer->update([
                    'image' => $filename,
                ]);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.profile updated'),
                'customer' => $customer->refresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function show()
    {
        $customer = Auth::guard('customers')->user();

        return response()->json([
            'success' => true,
            'message' => 'customer',
            'customer' => $customer->load('defaultAddress'),
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => ['required', 'string', 'min:8'],
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'same:password', 'string', 'min:8'],
        ]);
        $customer = Auth::guard('customers')->user();
        if (! Hash::check($request->old_password, $customer->password)) {
            return response()->json([
                'success' => false,
                'message' => __('responses.The password is incorrect'),
            ], 400);
        }
        try {
            DB::beginTransaction();
            $customer->update([
                'password' => Hash::make($request->password),
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.password changed successfully'),
                'customer' => $customer->refresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function changeCurrency(Request $request)
    {
        $request->validate([
            'currency' => 'required|string',
        ]);
        $customer = Auth::guard('customers')->user();
        $newCurrency = $request->currency;
        $oldCurrency = session('current_currency', 'SAR'); // الافتراضي SAR لو ما كان في سيشن

        // لا تحاول التغيير إذا العملة نفسها
        if ($newCurrency === $oldCurrency) {
            // تأكد من تحديث currency في حساب المستخدم أيضاً حتى لو نفس العملة
            if ($customer->currency !== $newCurrency) {
                $customer->update(['currency' => $newCurrency]);
            }

            return response()->json([
                'success' => true,
                'message' => __('responses.Currency already set to this currency'),
                'currency' => $newCurrency,
            ]);
        }

        $apiKey = config('services.exchange_rate.api_key');
        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'message' => __('responses.Exchange Rate API unavailable, cannot change currency at this time.'),
            ], 400);
        }

        $cacheKey = "exchange_rate_{$oldCurrency}_{$newCurrency}";

        try {
            // نفس منطق الكاش كما في CurrencyService
            $rate = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addDay(), function () use ($apiKey, $oldCurrency, $newCurrency) {
                try {
                    $response = \Illuminate\Support\Facades\Http::get("https://v6.exchangerate-api.com/v6/{$apiKey}/latest/{$oldCurrency}");
                    if ($response->successful() && isset($response->json()['conversion_rates'])) {
                        $data = $response->json();

                        if (! isset($data['conversion_rates'][$newCurrency])) {
                            // في حال العملة غير مدعومة
                            return null;
                        }

                        return $data['conversion_rates'][$newCurrency];
                    } else {
                        return null;
                    }
                } catch (\Exception $e) {
                    return null;
                }
            });

            // إذا العملة غير مدعومة أو فشل في جلب الريت
            if (is_null($rate)) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.Selected currency not supported.'),
                ], 400);
            }

            // حفظ العملة الجديدة بالسيشن للمستخدم
            session(['current_currency' => $newCurrency]);

            // تحديث currency للمستخدم في قاعدة البيانات
            $customer->update(['currency' => $newCurrency]);

            return response()->json([
                'success' => true,
                'message' => __('responses.Currency changed successfully'),
                'currency' => $newCurrency,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.Exchange Rate API unavailable, cannot change currency at this time.'),
            ], 400);
        }
    }

    public function deleteAccount()
    {
        try {
            DB::beginTransaction();
            $customer = Auth::guard('customers')->user();
            $customer->update([
                'status' => false,
            ]);
            $customer->tokens()->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.account deleted successfully'),
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
