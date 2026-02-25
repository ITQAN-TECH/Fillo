<?php

namespace App\Models;

use App\Facades\Currency;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'customer_address_id',
        'country_id',
        'city_id',
        'full_address',
        'phone',
        'national_address_short_number',
        'coupon_id',
        'coupon_code',
        'order_number',
        'subtotal_price',
        'discount_percentage',
        'discount_amount',
        'subtotal_price_after_discount',
        'shipping_fee',
        'total_price',
        'order_status',
        'cancellation_reason',
        'admin_notes',
    ];

    protected $casts = [
        'subtotal_price' => 'decimal:2',
        'discount_percentage' => 'integer',
        'discount_amount' => 'decimal:2',
        'subtotal_price_after_discount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    protected $with = [
        'customer',
        'customerAddress',
        'country',
        'city',
        'coupon',
        'items',
    ];

    protected $hidden = [
        'subtotal_price',
        'discount_amount',
        'subtotal_price_after_discount',
        'shipping_fee',
        'total_price',
    ];

    protected $appends = [
        'converted_subtotal_price',
        'converted_discount_amount',
        'converted_subtotal_price_after_discount',
        'converted_shipping_fee',
        'converted_total_price',
    ];

    public function getConvertedSubtotalPriceAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->subtotal_price * $rate, 2);
    }

    public function getConvertedDiscountAmountAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->discount_amount * $rate, 2);
    }

    public function getConvertedSubtotalPriceAfterDiscountAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->subtotal_price_after_discount * $rate, 2);
    }

    public function getConvertedShippingFeeAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->shipping_fee * $rate, 2);
    }

    public function getConvertedTotalPriceAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->total_price * $rate, 2);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerAddress()
    {
        return $this->belongsTo(CustomerAddress::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function cancellationRequests()
    {
        return $this->hasMany(OrderCancellationRequest::class);
    }

    public function latestCancellationRequest()
    {
        return $this->hasOne(OrderCancellationRequest::class)->latestOfMany();
    }
}
