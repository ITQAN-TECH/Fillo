<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\Package;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        $report = [];
        $report['total_customers_count'] = Customer::count();
        $report['total_active_customers_count'] = Customer::where('status', true)->count();
        $report['total_customer_identities_count'] = CustomerIdentity::where('status', 'pending')->count();
        $report['total_revenue_in_this_month'] = Subscription::whereMonth('created_at', now()->month)->sum('price');

        return response()->json([
            'success' => true,
            'message' => __('responses.all reports'),
            'report' => $report,
        ]);
    }

    public function generalReport()
    {
        $monthlyData = [];
        $previousMonthNewCustomers = 0;

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $month = $date->format('Y-m');
            $monthName = $date->locale(app()->getLocale())->translatedFormat('F Y');

            // عدد المستخدمين المسجلين في هذا الشهر
            $newCustomers = Customer::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            // إجمالي المستخدمين حتى نهاية هذا الشهر
            $totalCustomers = Customer::where('created_at', '<=', $date->endOfMonth())
                ->count();

            // حساب معدل النمو مقارنة مع الشهر الماضي
            $growthRate = null;
            if ($previousMonthNewCustomers > 0) {
                $growthRate = round((($newCustomers - $previousMonthNewCustomers) / $previousMonthNewCustomers) * 100, 1);
            } elseif ($previousMonthNewCustomers == 0 && $newCustomers > 0) {
                $growthRate = 100; // نمو 100% إذا كان الشهر السابق صفر والحالي فيه مستخدمين
            } elseif ($previousMonthNewCustomers == 0 && $newCustomers == 0) {
                $growthRate = 0; // لا يوجد نمو
            }

            $monthlyData[] = [
                'month' => $month,
                'month_name' => $monthName,
                'new_customers' => $newCustomers,
                'total_customers' => $totalCustomers,
                'growth_rate' => $growthRate, // معدل النمو بالنسبة المئوية
            ];

            // حفظ عدد المستخدمين الجدد للشهر الحالي ليصبح الشهر السابق في التكرار القادم
            $previousMonthNewCustomers = $newCustomers;
        }

        // حساب معدل النمو للشهر الحالي مقارنة بالشهر الماضي
        $currentMonthNewCustomers = Customer::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $lastMonthNewCustomers = Customer::whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->count();

        $currentMonthGrowthRate = null;
        if ($lastMonthNewCustomers > 0) {
            $currentMonthGrowthRate = round((($currentMonthNewCustomers - $lastMonthNewCustomers) / $lastMonthNewCustomers) * 100, 1);
        } elseif ($lastMonthNewCustomers == 0 && $currentMonthNewCustomers > 0) {
            $currentMonthGrowthRate = 100;
        } elseif ($lastMonthNewCustomers == 0 && $currentMonthNewCustomers == 0) {
            $currentMonthGrowthRate = 0;
        }

        $totalCustomers = Customer::count();

        $report = [
            'total_customers' => $totalCustomers,
            'active_customers' => Customer::where('status', true)->count(),
            'inactive_customers' => Customer::where('status', false)->count(),
            'average_between_active_and_inactive_customers' => round(Customer::where('status', true)->count() /
                ($totalCustomers == 0 ? 1 : $totalCustomers) * 100, 1),
            'current_month_new_customers' => $currentMonthNewCustomers,
            'last_month_new_customers' => $lastMonthNewCustomers,
            'current_month_growth_rate' => $currentMonthGrowthRate,
        ];

        // الباقات الموجودة + عدد ونسبة الذكور والإناث في كل باقة (حسب الاشتراك النشط حالياً)
        $packages = Package::orderBy('id')->get();
        $subscriptionsByPackage = [];

        foreach ($packages as $package) {
            $malesCount = Customer::whereHas('activeSubscription', function ($q) use ($package) {
                $q->where('package_id', $package->id);
            })->where('gender', 'male')->count();

            $femalesCount = Customer::whereHas('activeSubscription', function ($q) use ($package) {
                $q->where('package_id', $package->id);
            })->where('gender', 'female')->count();

            $total = $malesCount + $femalesCount;
            $percentage = $totalCustomers > 0 ? round(($total / $totalCustomers) * 100, 2) : 0;

            $subscriptionsByPackage[] = [
                'package_id' => $package->id,
                'package_ar_name' => $package->ar_name,
                'package_en_name' => $package->en_name,
                'total' => $total,
                'males_count' => $malesCount,
                'females_count' => $femalesCount,
                'percentage' => $percentage,
            ];
        }

        // الاشتراك المجاني: مستخدمون بدون اشتراك نشط حالياً
        $noSubscriptionMales = Customer::whereDoesntHave('activeSubscription')->where('gender', 'male')->count();
        $noSubscriptionFemales = Customer::whereDoesntHave('activeSubscription')->where('gender', 'female')->count();
        $noSubscriptionTotal = $noSubscriptionMales + $noSubscriptionFemales;
        $noSubscriptionPercentage = $totalCustomers > 0 ? round(($noSubscriptionTotal / $totalCustomers) * 100, 2) : 0;

        $subscriptionsByPackage[] = [
            'package_id' => null,
            'package_ar_name' => trans('responses.free_subscription', [], 'ar'),
            'package_en_name' => trans('responses.free_subscription', [], 'en'),
            'total' => $noSubscriptionTotal,
            'males_count' => $noSubscriptionMales,
            'females_count' => $noSubscriptionFemales,
            'percentage' => $noSubscriptionPercentage,
        ];

        return response()->json([
            'success' => true,
            'message' => __('responses.general report'),
            'report' => $report,
            'monthly_customers_growth' => $monthlyData,
            'subscriptions_by_package' => $subscriptionsByPackage,
        ]);
    }

    public function topCountriesReport(Request $request)
    {
        $request->validate([
            'period' => 'sometimes|nullable|in:last_7_days,last_month,all_time',
        ]);

        $period = $request->input('period', 'all_time');

        // بناء الـ query حسب الفترة المطلوبة
        $query = Customer::query();

        switch ($period) {
            case 'last_7_days':
                $query->where('created_at', '>=', Carbon::now()->subDays(7));
                break;
            case 'last_month':
                $query->where('created_at', '>=', Carbon::now()->subMonth());
                break;
            case 'all_time':
            default:
                // لا نضيف أي شرط للفترة الزمنية
                break;
        }

        // جلب أكثر 5 دول فيها مستخدمين
        $topCountries = Country::withCount(['customers' => function ($q) use ($query) {
            $q->whereIn('id', $query->pluck('id'));
        }])
            ->having('customers_count', '>', 0)
            ->orderBy('customers_count', 'desc')
            ->limit(5)
            ->get();

        // حساب إجمالي المستخدمين في الفترة المحددة
        $totalCustomersInPeriod = $query->count();

        // حساب إجمالي المستخدمين في أكثر 5 دول
        $totalCustomersInTopCountries = 0;

        // تحويل البيانات
        $countriesData = $topCountries->map(function ($country) use ($query, $totalCustomersInPeriod, &$totalCustomersInTopCountries) {
            // عدد الذكور في هذه الدولة
            $malesCount = (clone $query)->where('country_id', $country->id)
                ->where('gender', 'male')
                ->count();

            // عدد الإناث في هذه الدولة
            $femalesCount = (clone $query)->where('country_id', $country->id)
                ->where('gender', 'female')
                ->count();

            // إجمالي المستخدمين في هذه الدولة
            $totalCustomers = $malesCount + $femalesCount;

            // إضافة إلى المجموع الكلي
            $totalCustomersInTopCountries += $totalCustomers;

            // حساب النسبة المئوية من إجمالي المستخدمين
            $percentage = $totalCustomersInPeriod > 0
                ? round(($totalCustomers / $totalCustomersInPeriod) * 100, 2)
                : 0;

            return [
                'country_id' => $country->id,
                'country_name' => $country->name,
                'total_customers' => $totalCustomers,
                'males_count' => $malesCount,
                'females_count' => $femalesCount,
                'percentage' => $percentage,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => __('responses.top countries report'),
            'period' => $period,
            'total_customers_in_top_5_countries' => $totalCustomersInTopCountries,
            'total_customers_in_period' => $totalCustomersInPeriod,
            'countries' => $countriesData,
        ]);
    }

    public function ageGroupsReport(Request $request)
    {
        $request->validate([
            'period' => 'sometimes|nullable|in:last_7_days,last_month,all_time',
        ]);

        $period = $request->input('period', 'all_time');

        $query = Customer::query()->whereNotNull('birthdate');

        switch ($period) {
            case 'last_7_days':
                $query->where('created_at', '>=', Carbon::now()->subDays(7));
                break;
            case 'last_month':
                $query->where('created_at', '>=', Carbon::now()->subMonth());
                break;
            case 'all_time':
            default:
                break;
        }

        $totalInPeriod = $query->count();

        $now = Carbon::now();

        $ageGroups = [
            [
                'key' => 'under_18',
                'label' => __('responses.age_under_18'),
                'range' => '< 18',
                'birthdate_from' => null,
                'birthdate_to' => $now->copy()->subYears(18),
                'compare' => 'gt', // birthdate > to (أصغر من 18)
            ],
            [
                'key' => '18_24',
                'label' => __('responses.age_18_24'),
                'range' => '18-24',
                'birthdate_from' => $now->copy()->subYears(25),
                'birthdate_to' => $now->copy()->subYears(18),
                'compare' => 'between',
            ],
            [
                'key' => '25_34',
                'label' => __('responses.age_25_34'),
                'range' => '25-34',
                'birthdate_from' => $now->copy()->subYears(35),
                'birthdate_to' => $now->copy()->subYears(25),
                'compare' => 'between',
            ],
            [
                'key' => '35_44',
                'label' => __('responses.age_35_44'),
                'range' => '35-44',
                'birthdate_from' => $now->copy()->subYears(45),
                'birthdate_to' => $now->copy()->subYears(35),
                'compare' => 'between',
            ],
            [
                'key' => '45_64',
                'label' => __('responses.age_45_64'),
                'range' => '45-64',
                'birthdate_from' => $now->copy()->subYears(65),
                'birthdate_to' => $now->copy()->subYears(45),
                'compare' => 'between',
            ],
            [
                'key' => '65_plus',
                'label' => __('responses.age_65_plus'),
                'range' => '65+',
                'birthdate_from' => null,
                'birthdate_to' => $now->copy()->subYears(65),
                'compare' => 'lte', // birthdate <= to (65 وأكثر)
            ],
        ];

        $result = [];

        foreach ($ageGroups as $group) {
            $q = (clone $query);

            if ($group['compare'] === 'between') {
                $q->where('birthdate', '>', $group['birthdate_from'])
                    ->where('birthdate', '<=', $group['birthdate_to']);
            } elseif ($group['compare'] === 'gt') {
                $q->where('birthdate', '>', $group['birthdate_to']);
            } else {
                $q->where('birthdate', '<=', $group['birthdate_to']);
            }

            $malesCount = (clone $q)->where('gender', 'male')->count();
            $femalesCount = (clone $q)->where('gender', 'female')->count();
            $total = $malesCount + $femalesCount;

            $percentage = $totalInPeriod > 0 ? round(($total / $totalInPeriod) * 100, 2) : 0;

            $result[] = [
                'key' => $group['key'],
                'label' => $group['label'],
                'range' => $group['range'],
                'total' => $total,
                'males_count' => $malesCount,
                'females_count' => $femalesCount,
                'percentage' => $percentage,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => __('responses.age groups report'),
            'period' => $period,
            'total_customers_in_period' => $totalInPeriod,
            'age_groups' => $result,
        ]);
    }

    public function identityReport()
    {
        $result = [];
        $result['total_approved_identities_count_in_this_month'] = CustomerIdentity::where('status', 'approved')->whereMonth('created_at', now()->month)->count();
        $result['total_approved_identities_count_last_month'] = CustomerIdentity::where('status', 'approved')->whereMonth('created_at', now()->subMonth()->month)->count();
        $result['total_approved_identities_count'] = CustomerIdentity::where('status', 'approved')->count();
        $result['total_identities_count'] = CustomerIdentity::count();
        $result['average_identity_approval_rate'] = round($result['total_approved_identities_count'] / ($result['total_identities_count'] == 0 ? 1 : $result['total_identities_count']) * 100, 2);

        return response()->json([
            'success' => true,
            'message' => __('responses.identity report'),
            'result' => $result,
        ]);
    }
}
