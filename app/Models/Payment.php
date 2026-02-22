<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
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

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
