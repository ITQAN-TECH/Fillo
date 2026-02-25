<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    protected $fillable = [
        'ar_title',
        'en_title',
        'image',
        'type',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected static function booted()
    {

        static::deleted(function (self $model) {
            if ($model->image) {
                Storage::delete('public/media/'.$model->image);
            }
        });
    }

    public function subCategories()
    {
        return $this->hasMany(SubCategory::class);
    }
}
