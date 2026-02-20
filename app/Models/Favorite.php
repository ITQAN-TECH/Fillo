<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    protected $fillable = [
        'follower_id',
        'following_id',
    ];

    protected $casts = [
        'follower_id' => 'integer',
        'following_id' => 'integer',
    ];

    public function follower()
    {
        return $this->belongsTo(Customer::class, 'follower_id');
    }

    public function following()
    {
        return $this->belongsTo(Customer::class, 'following_id');
    }
}
