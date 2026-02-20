<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Country extends Model
{
    protected $fillable = [
        'ar_name',
        'en_name',
        'flag',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected static function booted()
    {
        static::deleted(function (self $model) {
            if ($model->flag) {
                Storage::delete('public/media/' . $model->flag);
            }
        });
    }

    public function cities()
    {
        return $this->hasMany(City::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function getNameAttribute()
    {
        return app()->getLocale() == 'ar' ? $this->ar_name : $this->en_name;
    }
}
