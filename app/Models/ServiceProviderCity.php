<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProviderCity extends Model
{
    protected $fillable = [
        'service_provider_id',
        'city_id',
    ];

    protected $with = [
        'city',
    ];

    public function serviceProvider()
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
