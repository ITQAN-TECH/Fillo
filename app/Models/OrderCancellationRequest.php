<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderCancellationRequest extends Model
{
    protected $fillable = [
        'order_id',
        'customer_id',
        'customer_reason',
        'status',
        'cancellation_reason',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    protected $with = [
        'order',
        'customer',
        'reviewer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
