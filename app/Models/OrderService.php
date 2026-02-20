<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Facades\Currency;

class OrderService extends Model
{
    protected $fillable = [
        'service_id',
        'customer_id',
        'coupon_id',
        'customer_address_id',
        'coupon_code',
        'service_provider_price',
        'sale_price',
        'profit_amount',
        'discount_percentage',
        'discount_amount',
        'service_provider_price_after_discount',
        'sale_price_after_discount',
        'profit_amount_after_discount',
        'order_date',
        'delivery_date',
        'order_status',
    ];

    protected $hidden = [
        'service_provider_price',
        'sale_price',
        'profit_amount',
        'discount_amount',
        'service_provider_price_after_discount',
        'sale_price_after_discount',
        'profit_amount_after_discount',
    ];

    protected $appends = [
        'converted_service_provider_price',
        'converted_sale_price',
        'converted_profit_amount',
        'converted_discount_amount',
        'converted_service_provider_price_after_discount',
        'converted_sale_price_after_discount',
        'converted_profit_amount_after_discount',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'delivery_date' => 'datetime',
        'order_status' => 'string',
        'service_provider_price' => 'float',
        'sale_price' => 'float',
        'profit_amount' => 'float',
        'discount_amount' => 'float',
        'discount_percentage' => 'integer',
        'service_provider_price_after_discount' => 'float',
        'sale_price_after_discount' => 'float',
        'profit_amount_after_discount' => 'float',
    ];

    protected $with = [
        'service',
        'customer',
        'coupon',
        'customer_address',
    ];

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

    public function getConvertedDiscountAmountAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->discount_amount * $rate, 2);
    }

    public function getConvertedServiceProviderPriceAfterDiscountAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->service_provider_price_after_discount * $rate, 2);
    }

    public function getConvertedSalePriceAfterDiscountAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->sale_price_after_discount * $rate, 2);
    }

    public function getConvertedProfitAmountAfterDiscountAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->profit_amount_after_discount * $rate, 2);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function customerAddress()
    {
        return $this->belongsTo(CustomerAddress::class);
    }
}
