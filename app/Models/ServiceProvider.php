<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ServiceProvider extends Model
{
    protected $fillable = [
        'country_id',
        'city_id',
        'name',
        'store_name',
        'type',
        'phone',
        'email',
        'image',
        'full_address',
        'specialization',
        'working_hours_start',
        'working_hours_end',
        'daily_orders_count',
        'id_file',
        'commercial_id_file',
        'service_practice_certificate_file',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'working_hours_start' => 'datetime:H:i:s',
        'working_hours_end' => 'datetime:H:i:s',
        'daily_orders_count' => 'integer',
    ];

    protected $with = [
        'country',
        'city',
        // 'citiesOfWorking'
    ];

    protected static function booted()
    {
        static::deleted(function (self $model) {
            if ($model->image) {
                Storage::delete('public/media/' . $model->image);
            }
            if ($model->id_file) {
                Storage::delete('public/media/' . $model->id_file);
            }
            if ($model->commercial_id_file) {
                Storage::delete('public/media/' . $model->commercial_id_file);
            }
            if ($model->service_practice_certificate_file) {
                Storage::delete('public/media/' . $model->service_practice_certificate_file);
            }
        });

        static::retrieved(function (self $model) {
            $model->average_rate = $model->services()->count() > 0 ? round($model->services()->avg('average_rate'), 1) : 5.0;
            $model->save();
        });
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function citiesOfWorking()
    {
        return $this->belongsToMany(City::class, 'service_provider_cities', 'service_provider_id', 'city_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'service_provider_id');
    }
}
