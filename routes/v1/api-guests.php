<?php

use App\Http\Controllers\api\v1\guests\BannerController;
use App\Http\Controllers\api\v1\guests\CityController;
use App\Http\Controllers\api\v1\guests\CountryController;
use App\Http\Controllers\api\v1\guests\FaqController;
use App\Http\Controllers\api\v1\guests\PageController;
use App\Http\Controllers\api\v1\guests\CategoryController;
use App\Http\Controllers\api\v1\guests\SubCategoryController;
use App\Http\Controllers\api\v1\guests\ServiceController;
use App\Http\Controllers\api\v1\guests\ServiceProviderController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1/'], function () {

    // Pages & Settings Routes
    Route::get('pages', [PageController::class, 'show']);
    Route::get('settings', [PageController::class, 'settings']);

    // Countries Routes
    Route::get('countries', [CountryController::class, 'index']);
    Route::get('countries/{country_id}', [CountryController::class, 'show']);

    // Cities Routes
    Route::get('cities', [CityController::class, 'index']);
    Route::get('cities/{city_id}', [CityController::class, 'show']);

    // Faqs Routes
    Route::get('faqs', [FaqController::class, 'index']);
    Route::get('faqs/{faq_id}', [FaqController::class, 'show']);

    // Banners Routes
    Route::get('banners', [BannerController::class, 'index']);
    Route::get('banners/{banner_id}', [BannerController::class, 'show']);

    // Categories Routes
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category_id}', [CategoryController::class, 'show']);

    // Sub Categories Routes
    Route::get('sub_categories', [SubCategoryController::class, 'index']);
    Route::get('sub_categories/{subCategory_id}', [SubCategoryController::class, 'show']);

    // Services Routes
    Route::get('services', [ServiceController::class, 'index']);
    Route::get('services/{service_id}', [ServiceController::class, 'show']);

    // Service Providers Routes
    Route::get('service_providers', [ServiceProviderController::class, 'index']);
    Route::get('service_providers/{serviceProvider_id}', [ServiceProviderController::class, 'show']);

});
