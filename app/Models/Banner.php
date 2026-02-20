<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    protected $fillable = [
        'ar_title',
        'en_title',
        'image',
        'url',
        'status',
        'clicks',
    ];

    protected $casts = [
        'status' => 'boolean',
        'clicks' => 'integer',
    ];

    protected static function booted()
    {
        static::deleted(function (self $model) {
            if ($model->image) {
                Storage::delete('public/media/'.$model->image);
            }
        });
    }
}
