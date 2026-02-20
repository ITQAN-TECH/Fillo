<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SubCategory extends Model
{
    protected $fillable = [
        'category_id',
        'ar_title',
        'en_title',
        'image',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected static function booted() {
        static::deleted(function (self $model) {
            if ($model->image) {
                Storage::delete('public/media/' . $model->image);
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
