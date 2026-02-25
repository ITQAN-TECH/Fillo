<?php

use App\Http\Controllers\api\v1\admin\AdminController;
use App\Http\Controllers\api\v1\admin\AuthController;
use App\Http\Controllers\api\v1\admin\BannerController;
use App\Http\Controllers\api\v1\admin\BookingController;
use App\Http\Controllers\api\v1\admin\CategoryController;
use App\Http\Controllers\api\v1\admin\CityController;
use App\Http\Controllers\api\v1\admin\ColorController;
use App\Http\Controllers\api\v1\admin\CountryController;
use App\Http\Controllers\api\v1\admin\CouponController;
use App\Http\Controllers\api\v1\admin\CustomerController;
use App\Http\Controllers\api\v1\admin\FaqController;
use App\Http\Controllers\api\v1\admin\ForgetPasswordController;
use App\Http\Controllers\api\v1\admin\NotificationFromAdminController;
use App\Http\Controllers\api\v1\admin\OrderCancellationRequestController;
use App\Http\Controllers\api\v1\admin\OrderController;
use App\Http\Controllers\api\v1\admin\PageController;
use App\Http\Controllers\api\v1\admin\PaymentController;
use App\Http\Controllers\api\v1\admin\ProductController;
use App\Http\Controllers\api\v1\admin\ProfileController;
use App\Http\Controllers\api\v1\admin\RateController;
use App\Http\Controllers\api\v1\admin\ReportController;
use App\Http\Controllers\api\v1\admin\RoleController;
use App\Http\Controllers\api\v1\admin\ServiceController;
use App\Http\Controllers\api\v1\admin\ServiceProviderController;
use App\Http\Controllers\api\v1\admin\SettingController;
use App\Http\Controllers\api\v1\admin\SizeController;
use App\Http\Controllers\api\v1\admin\SubCategoryController;
use App\Http\Controllers\api\v1\admin\SupportChatController;
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

    // Booking Routes
    Route::get('bookings', [BookingController::class, 'index']);
    Route::get('bookings/{booking_id}', [BookingController::class, 'show']);
    Route::post('bookings/{booking_id}/confirm', [BookingController::class, 'confirmBooking']);
    Route::post('bookings/{booking_id}/complete', [BookingController::class, 'completeBooking']);
    Route::post('bookings/{booking_id}/cancel', [BookingController::class, 'cancelBooking']);

    // Order Routes
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order_id}', [OrderController::class, 'show']);
    Route::post('orders/{order_id}/confirm', [OrderController::class, 'confirmOrder']);
    Route::post('orders/{order_id}/reject', [OrderController::class, 'rejectOrder']);
    Route::post('orders/{order_id}/ship', [OrderController::class, 'shipOrder']);
    Route::post('orders/{order_id}/deliver', [OrderController::class, 'deliverOrder']);
    Route::post('orders/{order_id}/complete', [OrderController::class, 'completeOrder']);
    Route::post('orders/{order_id}/cancel', [OrderController::class, 'cancelOrder']);
    Route::post('orders/{order_id}/refund', [OrderController::class, 'refundOrder']);

    // Order Cancellation Request Routes
    Route::get('order_cancellation_requests', [OrderCancellationRequestController::class, 'index']);
    Route::get('order_cancellation_requests/{request_id}', [OrderCancellationRequestController::class, 'show']);
    Route::post('order_cancellation_requests/{request_id}/approve', [OrderCancellationRequestController::class, 'approve']);
    Route::post('order_cancellation_requests/{request_id}/reject', [OrderCancellationRequestController::class, 'reject']);

    // Payment Routes
    Route::get('payments', [PaymentController::class, 'index']);
    Route::get('payments/statistics', [PaymentController::class, 'statistics']);
    Route::get('payments/{payment_id}', [PaymentController::class, 'show']);

    // Rate Routes
    Route::delete('rates/{rate_id}', [RateController::class, 'destroy']);

    // Product Routes
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product_id}', [ProductController::class, 'show']);
    Route::post('products', [ProductController::class, 'store']);
    Route::post('products/{product_id}', [ProductController::class, 'update']);
    Route::delete('products/{product_id}', [ProductController::class, 'destroy']);
    Route::post('products/change_status/{product_id}', [ProductController::class, 'changeStatus']);
    Route::delete('products/delete_image/{image_id}', [ProductController::class, 'deleteImage']);
    Route::post('products/{product_id}/variants', [ProductController::class, 'addVariant']);
    Route::post('products/variants/{variant_id}', [ProductController::class, 'updateVariant']);
    Route::delete('products/variants/{variant_id}', [ProductController::class, 'deleteVariant']);

    // Color Routes
    Route::get('colors', [ColorController::class, 'index']);
    Route::get('colors/{color_id}', [ColorController::class, 'show']);
    Route::post('colors', [ColorController::class, 'store']);
    Route::post('colors/{color_id}', [ColorController::class, 'update']);
    Route::delete('colors/{color_id}', [ColorController::class, 'destroy']);
    Route::post('colors/change_status/{color_id}', [ColorController::class, 'changeStatus']);

    // Size Routes
    Route::get('sizes', [SizeController::class, 'index']);
    Route::get('sizes/{size_id}', [SizeController::class, 'show']);
    Route::post('sizes', [SizeController::class, 'store']);
    Route::post('sizes/{size_id}', [SizeController::class, 'update']);
    Route::delete('sizes/{size_id}', [SizeController::class, 'destroy']);
    Route::post('sizes/change_status/{size_id}', [SizeController::class, 'changeStatus']);

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
});

Route::group(['prefix' => 'v1/dashboard'], function () {

    // Auth Routes
    Route::post('login', [AuthController::class, 'login']);

    // Forget Password Routes
    Route::post('forget_password', [ForgetPasswordController::class, 'sendCodeForForgetPassword']);
    Route::post('set_otp_for_forget_password', [ForgetPasswordController::class, 'setOTPForForgetPassword']);
    Route::post('change_password', [ForgetPasswordController::class, 'changePassword']);
});
