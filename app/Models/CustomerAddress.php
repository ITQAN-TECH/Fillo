<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    protected $fillable = [
        'customer_id',
        'country_id',
        'city_id',
        'address_title',
        'full_address',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];
    
    protected $with = [
        'country',
        'city',
    ];
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
