<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    protected $fillable = [
        'ar_name',
        'en_name',
        'hex_code',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function productVariants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
