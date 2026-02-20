<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportChat extends Model
{
    use HasFactory;

    public $incrementing = false;

    public $fillable = [
        'customer_id',
        'sender_type',
        'message',
        'image',
        'audio',
        'read_at',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->id = Str::uuid();
        });
        static::deleted(function (self $model) {
            \Illuminate\Support\Facades\File::delete(public_path('storage/media/'.$model->image));
            \Illuminate\Support\Facades\File::delete(public_path('storage/media/'.$model->audio));
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
