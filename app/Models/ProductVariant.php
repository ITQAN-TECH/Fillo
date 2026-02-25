<?php

namespace App\Models;

use App\Facades\Currency;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'color_id',
        'size_id',
        'quantity',
        'sale_price',
        'variant_sku',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'sale_price' => 'decimal:2',
        'status' => 'boolean',
        'converted_sale_price' => 'float',
    ];

    protected $with = [
        'color',
        'size',
    ];

    protected $hidden = [
        'sale_price',
    ];

    protected $appends = [
        'converted_sale_price',
    ];

    public function getConvertedSalePriceAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->sale_price * $rate, 2);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    public function isAvailable()
    {
        return $this->status && $this->quantity > 0;
    }
}
