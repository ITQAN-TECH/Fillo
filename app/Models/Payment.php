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
        'payment_source',
        'transaction_id',
        'mf_payment_id',
        'mf_base_amount',
        'invoice_id',
        'amount',
        'refunded_amount',
        'currency',
        'status',
        'payment_response',
    ];

    protected $casts = [
        'amount' => 'float',
        'refunded_amount' => 'float',
        'mf_base_amount' => 'float',
        'status' => 'string',
        'payment_source' => 'string',
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
        'mf_payment_id',
        'mf_base_amount',
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
