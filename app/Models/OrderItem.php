<?php

namespace App\Models;

use App\Facades\Currency;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'price',
        'quantity',
        'total_price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    protected $with = [
        'product',
        'productVariant',
    ];

    protected $hidden = [
        'price',
        'total_price',
    ];

    protected $appends = [
        'converted_price',
        'converted_total_price',
    ];

    public function getConvertedPriceAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->price * $rate, 2);
    }

    public function getConvertedTotalPriceAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->total_price * $rate, 2);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
