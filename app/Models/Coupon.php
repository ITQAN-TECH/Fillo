<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'discount_percentage',
        'expiry_date',
        'type',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'expiry_date' => 'date:Y-m-d',
        'discount_percentage' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now())->where('status', true);
    }

    public function scopeNotExpired($query)
    {
        return $query->where('expiry_date', '>', now())->where('status', true);
    }

    public function scopeValid($query)
    {
        return $query->where('status', true)->where('expiry_date', '>', now())->where('expiry_date', '<', now());
    }

    public function scopeInvalid($query)
    {
        return $query->where('status', false)->where('expiry_date', '<', now())->where('expiry_date', '>', now());
    }
}
