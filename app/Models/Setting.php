<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'phone_number',
        'email',
        'shipping_fee',
    ];

    protected $casts = [
        'shipping_fee' => 'float',
    ];
}
