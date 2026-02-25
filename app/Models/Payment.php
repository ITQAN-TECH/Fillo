<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Facades\Currency;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'order_id',
        'payment_method',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'payment_response',
    ];

    protected $casts = [
        'amount' => 'float',
        'status' => 'string',
    ];

    protected $with = [
        'booking',
        'order',
    ];

    protected $appends = [
        'converted_amount',
    ];

    protected $hidden = [
        'amount',
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
}
