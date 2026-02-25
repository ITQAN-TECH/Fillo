<?php

namespace App\Models;

use App\Facades\Currency;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = [
        'customer_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'price',
        'total_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
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
        'status',
        'available_quantity',
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

    protected static function booted()
    {
        static::retrieved(function (self $model) {
            $model->price = $model->productVariant->sale_price;
            $model->total_price = $model->productVariant->sale_price * $model->quantity;
            $model->save();
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function getStatusAttribute()
    {
        return $this->productVariant->status && $this->product->status && $this->productVariant->quantity > 0;
    }

    public function getAvailableQuantityAttribute()
    {
        return $this->productVariant->quantity > $this->quantity ? $this->productVariant->quantity - $this->quantity : 0;
    }
}
