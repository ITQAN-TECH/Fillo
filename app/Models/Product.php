<?php

namespace App\Models;

use App\Facades\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'sub_category_id',
        'ar_name',
        'en_name',
        'ar_description',
        'en_description',
        'ar_small_description',
        'en_small_description',
        'sku',
        'sale_price',
        'status',
        'average_rate',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'status' => 'boolean',
        'is_favorite' => 'boolean',
        'converted_sale_price' => 'float',
    ];

    protected $with = [
        'category',
        'subCategory',
        'features',
        'images',
    ];

    protected $appends = [
        'is_favorite',
        'converted_sale_price',
    ];

    protected $hidden = [
        'sale_price',
    ];

    public function getConvertedSalePriceAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->sale_price * $rate, 2);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function getTotalQuantityAttribute()
    {
        return $this->variants()->sum('quantity');
    }

    public function getAvailableColorsAttribute()
    {
        return $this->variants()
            ->with('color')
            ->where('status', true)
            ->where('quantity', '>', 0)
            ->get()
            ->pluck('color')
            ->unique('id');
    }

    public function getAvailableSizesAttribute()
    {
        return $this->variants()
            ->with('size')
            ->where('status', true)
            ->where('quantity', '>', 0)
            ->get()
            ->pluck('size')
            ->unique('id');
    }

    public function getIsFavoriteAttribute()
    {
        return Favorite::where('customer_id', Auth::guard('customers')->id())->where('product_id', $this->id)->exists();
    }

    public function rates()
    {
        return $this->morphMany(Rate::class, 'rateable');
    }

    public function features()
    {
        return $this->morphMany(Feature::class, 'featureable');
    }

    public function isActive()
    {
        return $this->status == true && $this->variants()->where('status', true)->where('quantity', '>', 0)->exists();
    }

    public function scopeIsActive($query)
    {
        return $query->where('status', true)->whereHas('variants', function ($query) {
            $query->where('status', true)->where('quantity', '>', 0);
        });
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
