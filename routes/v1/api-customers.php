<?php

use App\Http\Controllers\api\v1\customers\AuthController;
use App\Http\Controllers\api\v1\customers\CartController;
use App\Http\Controllers\api\v1\customers\CustomerAddressController;
use App\Http\Controllers\api\v1\customers\FavoriteController;
use App\Http\Controllers\api\v1\customers\FCMToken\FCMTokenController;
use App\Http\Controllers\api\v1\customers\ForgetPasswordController;
use App\Http\Controllers\api\v1\customers\notifications\database\DatabaseNotificationController;
use App\Http\Controllers\api\v1\customers\OrderController;
use App\Http\Controllers\api\v1\customers\ProfileController;
use App\Http\Controllers\api\v1\customers\ServiceController;
use App\Http\Controllers\api\v1\customers\SupportChatController;
use App\Http\Middleware\CheckForCustomerStatus;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1/customers', 'middleware' => ['auth:customers', CheckForCustomerStatus::class]], function () {

    // Auth Route
    Route::post('logout', [AuthController::class, 'logout']);

    // Profile Route
    Route::post('change_password', [ProfileController::class, 'changePassword']);
    Route::post('edit_profile', [ProfileController::class, 'editProfile']);
    Route::get('profile', [ProfileController::class, 'show']);
    Route::post('delete_account', [ProfileController::class, 'deleteAccount']);
    Route::post('change_interests', [ProfileController::class, 'changeInterests']);
    Route::post('sync_images_and_videos', [ProfileController::class, 'syncImagesAndVideos']);
    Route::post('change_currency', [ProfileController::class, 'changeCurrency']);

    // Database Notification Routes
    Route::get('notifications', [DatabaseNotificationController::class, 'index']);
    Route::get('notifications/mark_all_as_read', [DatabaseNotificationController::class, 'markAllAsRead']);
    Route::post('notifications/delete', [DatabaseNotificationController::class, 'delete']);
    Route::get('notifications/{id}', [DatabaseNotificationController::class, 'show']);
    Route::post('notifications/toggle', [DatabaseNotificationController::class, 'toggleNotifications']);

    // Customer Address Routes
    Route::get('addresses', [CustomerAddressController::class, 'index']);
    Route::get('addresses/{address_id}', [CustomerAddressController::class, 'show']);
    Route::post('addresses', [CustomerAddressController::class, 'store']);
    Route::post('addresses/{address_id}', [CustomerAddressController::class, 'update']);
    Route::delete('addresses/{address_id}', [CustomerAddressController::class, 'destroy']);
    Route::post('addresses/{address_id}/set_default', [CustomerAddressController::class, 'setDefaultAddress']);

    // Change FCM Token
    Route::post('store_fcm_token', [FCMTokenController::class, 'store']);
    Route::delete('fcm_token', [FCMTokenController::class, 'destroy']);

    // Support Chats Routes
    Route::get('support_chats', [SupportChatController::class, 'index']);
    Route::post('support_chat', [SupportChatController::class, 'show']);
    Route::post('support_chat/send', [SupportChatController::class, 'store']);
    Route::get('support_chat/read_message/{message_id}', [SupportChatController::class, 'readMessage']);

    // Favorite Routes
    Route::get('favorites', [FavoriteController::class, 'index']);
    Route::post('favorites', [FavoriteController::class, 'store']);
    Route::delete('favorites/{service_id}/service', [FavoriteController::class, 'destroyService']);
    Route::delete('favorites/{product_id}/product', [FavoriteController::class, 'destroyProduct']);
    Route::delete('empty_favorites', [FavoriteController::class, 'empty']);

    // Cart Routes
    Route::get('cart', [CartController::class, 'show']);
    Route::post('cart', [CartController::class, 'add']);
    Route::post('cart/{cart_id}/plus', [CartController::class, 'plus']);
    Route::post('cart/{cart_id}/minus', [CartController::class, 'minus']);
    Route::delete('cart/{cart_id}', [CartController::class, 'destroy']);
    Route::delete('empty_cart', [CartController::class, 'destroyAll']);

    // Order Routes
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order_id}', [OrderController::class, 'show']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::post('orders/{order_id}/request_cancellation', [OrderController::class, 'requestCancellation']);
    Route::get('cancellation_requests', [OrderController::class, 'myCancellationRequests']);
    Route::get('cancellation_requests/{request_id}', [OrderController::class, 'showCancellationRequest']);

    // Service Booking Routes
    Route::post('services/calculate_price', [ServiceController::class, 'calculatePrice']);
    Route::post('services/initiate_booking', [ServiceController::class, 'initiateBooking']);
    Route::post('services/pay_booking', [ServiceController::class, 'payBooking']);
    Route::get('bookings', [ServiceController::class, 'myBookings']);
    Route::get('bookings/{booking_id}', [ServiceController::class, 'bookingDetails']);
    Route::post('bookings/{booking_id}/cancel', [ServiceController::class, 'cancelBooking']);
    Route::post('bookings/{booking_id}/rate', [ServiceController::class, 'rateService']);
});
Route::group(['prefix' => 'v1/customers/'], function () {

    // Auth Routes
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('set_otp', [AuthController::class, 'setOTP']);
    Route::post('resend_otp', [AuthController::class, 'resendOTP']);

    // Forget Password Routes
    Route::post('forget_password/set_phone', [ForgetPasswordController::class, 'setPhone']);
    Route::post('forget_password/set_otp', [ForgetPasswordController::class, 'setOTP']);
    Route::post('forget_password/change_password', [ForgetPasswordController::class, 'changePassword']);
});
