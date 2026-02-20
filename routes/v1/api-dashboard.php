<?php

use App\Http\Controllers\api\v1\admin\AdminController;
use App\Http\Controllers\api\v1\admin\AuthController;
use App\Http\Controllers\api\v1\admin\BannerController;
use App\Http\Controllers\api\v1\admin\CityController;
use App\Http\Controllers\api\v1\admin\CountryController;
use App\Http\Controllers\api\v1\admin\CategoryController;
use App\Http\Controllers\api\v1\admin\CustomerController;
use App\Http\Controllers\api\v1\admin\SubCategoryController;
use App\Http\Controllers\api\v1\admin\FaqController;
use App\Http\Controllers\api\v1\admin\ForgetPasswordController;
use App\Http\Controllers\api\v1\admin\NotificationFromAdminController;
use App\Http\Controllers\api\v1\admin\PageController;
use App\Http\Controllers\api\v1\admin\ProfileController;
use App\Http\Controllers\api\v1\admin\ReportController;
use App\Http\Controllers\api\v1\admin\RoleController;
use App\Http\Controllers\api\v1\admin\SettingController;
use App\Http\Controllers\api\v1\admin\SupportChatController;
use App\Http\Controllers\api\v1\admin\ServiceProviderController;
use App\Http\Controllers\api\v1\admin\ServiceController;
use App\Http\Controllers\api\v1\admin\RateController;
use App\Http\Controllers\api\v1\admin\CouponController;
use App\Http\Middleware\CheckForAdminStatus;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1/dashboard/', 'middleware' => ['auth:admins', CheckForAdminStatus::class]], function () {

    // Auth Route
    Route::post('logout', [AuthController::class, 'logout']);

    // Profile Route
    Route::post('edit_profile', [ProfileController::class, 'editProfile']);
    Route::post('edit_password', [ProfileController::class, 'editPassword']);
    Route::get('profile', [ProfileController::class, 'show']);

    // Role Routes
    Route::get('roles', [RoleController::class, 'index']);
    Route::get('roles/all_permissions', [RoleController::class, 'allPermissionsForAdmin']);
    Route::get('roles/{role_id}', [RoleController::class, 'show']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::post('roles/{role_id}', [RoleController::class, 'update']);
    Route::delete('roles/{role_id}', [RoleController::class, 'destroy']);

    // Admin Routes
    Route::get('admins', [AdminController::class, 'index']);
    Route::get('admins/{admin_id}', [AdminController::class, 'show']);
    Route::post('admins', [AdminController::class, 'store']);
    Route::post('admins/{admin_id}', [AdminController::class, 'update']);
    Route::delete('admins/{admin_id}', [AdminController::class, 'destroy']);
    Route::post('admins/change_status/{admin_id}', [AdminController::class, 'changeStatus']);
    Route::post('admins/edit_password/{admin_id}', [AdminController::class, 'changePassword']);

    // Country Routes
    Route::get('countries', [CountryController::class, 'index']);
    Route::get('countries/{country_id}', [CountryController::class, 'show']);
    Route::post('countries', [CountryController::class, 'store']);
    Route::post('countries/{country_id}', [CountryController::class, 'update']);
    Route::delete('countries/{country_id}', [CountryController::class, 'destroy']);
    Route::post('countries/change_status/{country_id}', [CountryController::class, 'changeStatus']);

    // City Routes
    Route::get('cities', [CityController::class, 'index']);
    Route::get('cities/{city_id}', [CityController::class, 'show']);
    Route::post('cities', [CityController::class, 'store']);
    Route::post('cities/{city_id}', [CityController::class, 'update']);
    Route::delete('cities/{city_id}', [CityController::class, 'destroy']);
    Route::post('cities/change_status/{city_id}', [CityController::class, 'changeStatus']);

    // Category Routes
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/search', [CategoryController::class, 'search']);
    Route::get('categories/{category_id}', [CategoryController::class, 'show']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::post('categories/{category_id}', [CategoryController::class, 'update']);
    Route::delete('categories/{category_id}', [CategoryController::class, 'destroy']);
    Route::post('categories/change_status/{category_id}', [CategoryController::class, 'changeStatus']);

    // Sub Category Routes
    Route::get('sub_categories', [SubCategoryController::class, 'index']);
    Route::get('sub_categories/search', [SubCategoryController::class, 'search']);
    Route::get('sub_categories/{subCategory_id}', [SubCategoryController::class, 'show']);
    Route::post('sub_categories', [SubCategoryController::class, 'store']);
    Route::post('sub_categories/{subCategory_id}', [SubCategoryController::class, 'update']);
    Route::delete('sub_categories/{subCategory_id}', [SubCategoryController::class, 'destroy']);
    Route::post('sub_categories/change_status/{subCategory_id}', [SubCategoryController::class, 'changeStatus']);

    // Service Provider Routes
    Route::get('service_providers', [ServiceProviderController::class, 'index']);
    Route::get('service_providers/{service_provider_id}', [ServiceProviderController::class, 'show']);
    Route::post('service_providers', [ServiceProviderController::class, 'store']);
    Route::post('service_providers/{service_provider_id}', [ServiceProviderController::class, 'update']);
    Route::delete('service_providers/{service_provider_id}', [ServiceProviderController::class, 'destroy']);
    Route::post('service_providers/change_status/{service_provider_id}', [ServiceProviderController::class, 'changeStatus']);

    // Service Routes
    Route::get('services', [ServiceController::class, 'index']);
    Route::get('services/{service_id}', [ServiceController::class, 'show']);
    Route::post('services', [ServiceController::class, 'store']);
    Route::post('services/{service_id}', [ServiceController::class, 'update']);
    Route::delete('services/{service_id}', [ServiceController::class, 'destroy']);
    Route::post('services/change_status/{service_id}', [ServiceController::class, 'changeStatus']);
    Route::post('services/change_featured/{service_id}', [ServiceController::class, 'changeFeatured']);
    Route::delete('services/delete_image/{image_id}', [ServiceController::class, 'deleteImage']);

    // Coupon Routes
    Route::get('coupons', [CouponController::class, 'index']);
    Route::get('coupons/{coupon_id}', [CouponController::class, 'show']);
    Route::post('coupons', [CouponController::class, 'store']);
    Route::post('coupons/{coupon_id}', [CouponController::class, 'update']);
    Route::delete('coupons/{coupon_id}', [CouponController::class, 'destroy']);
    Route::post('coupons/change_status/{coupon_id}', [CouponController::class, 'changeStatus']);

    // Rate Routes
    Route::delete('rates/{rate_id}', [RateController::class, 'destroy']);

    // Faq Routes
    Route::get('faqs', [FaqController::class, 'index']);
    Route::get('faqs/{faq_id}', [FaqController::class, 'show']);
    Route::post('faqs', [FaqController::class, 'store']);
    Route::post('faqs/{faq_id}', [FaqController::class, 'update']);
    Route::delete('faqs/{faq_id}', [FaqController::class, 'destroy']);

    // Banner Routes
    Route::get('banners', [BannerController::class, 'index']);
    Route::get('banners/{banner_id}', [BannerController::class, 'show']);
    Route::post('banners', [BannerController::class, 'store']);
    Route::post('banners/{banner_id}', [BannerController::class, 'update']);
    Route::delete('banners/{banner_id}', [BannerController::class, 'destroy']);
    Route::post('banners/change_status/{banner_id}', [BannerController::class, 'changeStatus']);

    // Customer Routes
    Route::get('customers', [CustomerController::class, 'index']);
    Route::get('customers/search', [CustomerController::class, 'search']);
    Route::get('customers/{customer_id}', [CustomerController::class, 'show']);
    Route::post('customers/{customer_id}', [CustomerController::class, 'update']);
    Route::delete('customers/{customer_id}', [CustomerController::class, 'destroy']);
    Route::post('customers/change_status/{customer_id}', [CustomerController::class, 'changeStatus']);

    // Support Chats Routes
    Route::get('support_chats_with_customers', [SupportChatController::class, 'customersSupportChats']);
    Route::post('support_chats_with_customers', [SupportChatController::class, 'showCustomerSupportChat']);
    Route::post('support_chats_with_customers/send', [SupportChatController::class, 'createCustomerSupportChat']);

    // Notification From Admin Routes
    Route::get('notification_from_admins', [NotificationFromAdminController::class, 'index']);
    Route::get('notification_from_admins/{notification_id}', [NotificationFromAdminController::class, 'show']);
    Route::post('notification_from_admins', [NotificationFromAdminController::class, 'store']);
    Route::delete('notification_from_admins/{notification_id}', [NotificationFromAdminController::class, 'destroy']);

    // Setting Routes
    Route::get('settings', [SettingController::class, 'show']);
    Route::post('settings', [SettingController::class, 'update']);

    // Pages Routes
    Route::get('pages', [PageController::class, 'show']);
    Route::post('pages', [PageController::class, 'update']);

    // Report Routes
    Route::get('report', [ReportController::class, 'index']);
    Route::get('report/general_report', [ReportController::class, 'generalReport']);
    Route::get('report/top_countries', [ReportController::class, 'topCountriesReport']);
    Route::get('report/age_groups', [ReportController::class, 'ageGroupsReport']);
    Route::get('report/identity', [ReportController::class, 'identityReport']);
});

Route::group(['prefix' => 'v1/dashboard'], function () {

    // Auth Routes
    Route::post('login', [AuthController::class, 'login']);

    // Forget Password Routes
    Route::post('forget_password', [ForgetPasswordController::class, 'sendCodeForForgetPassword']);
    Route::post('set_otp_for_forget_password', [ForgetPasswordController::class, 'setOTPForForgetPassword']);
    Route::post('change_password', [ForgetPasswordController::class, 'changePassword']);
});
