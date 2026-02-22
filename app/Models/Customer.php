<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'is_phone_verified',
        'password',
        'status',
        'gender',
        'receive_notifications',
        'image',
        'otp',
        'currency',
    ];

    protected $hidden = [
        'password',
        'otp',
    ];

    protected $appends = [
        'un_read_notifications_count',
    ];

    protected $with = [];

    protected function casts()
    {
        return [
            'status' => 'boolean',
            'receive_notifications' => 'boolean',
            'password' => 'hashed',
            'is_phone_verified' => 'boolean',

        ];
    }

    protected static function booted()
    {
        if (env('APP_ENV') === 'local') {
            static::creating(function (self $model) {
                $model->otp = 1111;
            });
            static::updating(function (self $model) {
                $model->otp = 1111;
            });
        }

        static::deleted(function (self $model) {
            if ($model->image) {
                Storage::delete('public/media/' . $model->image);
            }
        });
    }

    public function getUnReadNotificationsCountAttribute()
    {
        return $this->notifications()->where('read_at', null)->count();
    }

    public function fcmTokens()
    {
        return $this->morphMany(FcmToken::class, 'tokenable');
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function defaultAddress()
    {
        return $this->hasOne(CustomerAddress::class)->where('is_default', true);
    }

    public function rates()
    {
        return $this->hasMany(Rate::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
