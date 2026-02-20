<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rate extends Model
{
    protected $fillable = [
        'rateable_id',
        'rateable_type',
        'rate',
        'customer_id',
        'comment',
    ];

    protected $casts = [
        'rate' => 'integer',
    ];

    public function rateable()
    {
        return $this->morphTo();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    protected static function booted()
    {
        static::created(function (self $model) {
            $model->rateable->update([
                'average_rate' => round($model->rateable->rates->avg('rate'), 1),
            ]);
        });

        static::updated(function (self $model) {
            $model->rateable->update([
                'average_rate' => round($model->rateable->rates->avg('rate'), 1),
            ]);
        });

        static::deleted(function (self $model) {
            if ($model->rateable->rates->count() == 0) {
                $model->rateable->update([
                    'average_rate' => 5.0,
                ]);
            } else {
                $model->rateable->update([
                    'average_rate' => round($model->rateable->rates->avg('rate'), 1),
                ]);
            }
        });
    }
}
