<?php

namespace App\Models;

use App\Facades\Currency;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'category_id',
        'sub_category_id',
        'service_provider_id',
        'ar_name',
        'en_name',
        'ar_description',
        'en_description',
        'service_provider_price',
        'sale_price',
        'profit_amount',
        'average_rate',
        'is_featured',
        'status',
    ];

    protected $withCount = [
        'rates',
    ];

    protected $hidden = [
        'service_provider_price',
        'sale_price',
        'profit_amount',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'status' => 'boolean',
        'profit_amount' => 'float',
        'service_provider_price' => 'float',
        'sale_price' => 'float',
        'average_rate' => 'float',
    ];

    protected $with = [
        'category',
        'subCategory',
        'serviceProvider',
        'images',
    ];

    protected $appends = [
        'converted_service_provider_price',
        'converted_sale_price',
        'converted_profit_amount'
    ];

    protected static function booted()
    {
        static::retrieved(function (self $model) {
            $model->average_rate = Rate::where('rateable_id', $model->id)->where('rateable_type', 'App\Models\Service')->count() > 0 ? round(Rate::where('rateable_id', $model->id)->where('rateable_type', 'App\Models\Service')->avg('rate'), 1) : 5.0;
        });
    }

    public function getConvertedServiceProviderPriceAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->service_provider_price * $rate, 2);
    }

    public function getConvertedSalePriceAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->sale_price * $rate, 2);
    }

    public function getConvertedProfitAmountAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->profit_amount * $rate, 2);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function serviceProvider()
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function rates()
    {
        return $this->morphMany(Rate::class, 'rateable');
    }
}
