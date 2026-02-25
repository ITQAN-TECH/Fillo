<?php

namespace App\Models;

use App\Facades\Currency;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'order_id',
        'payment_method',
        'transaction_id',
        'amount',
        'refunded_amount',
        'currency',
        'status',
        'payment_response',
    ];

    protected $casts = [
        'amount' => 'float',
        'refunded_amount' => 'float',
        'status' => 'string',
    ];

    protected $with = [
        'booking',
        'order',
    ];

    protected $appends = [
        'converted_amount',
        'converted_refunded_amount',
    ];

    protected $hidden = [
        'amount',
        'refunded_amount',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getConvertedAmountAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->amount * $rate, 2);
    }

    public function getConvertedRefundedAmountAttribute()
    {
        $rate = Currency::getRate('SAR');

        return round($this->refunded_amount * $rate, 2);
    }
}
