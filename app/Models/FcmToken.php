<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FcmToken extends Model
{
    protected $fillable = [
        'tokenable_id',
        'tokenable_type',
        'token',
        'device_id',
        'language',
    ];

    /**
     * Get the parent tokenable model (Customer, Investor, etc.).
     */
    public function tokenable()
    {
        return $this->morphTo();
    }
}
