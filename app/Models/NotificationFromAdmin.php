<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationFromAdmin extends Model
{
    protected $fillable = [
        'title',
        'desc',
        'target',
        'type',
        'schedule_at',
        'target_data',
        'is_sent',
        'type',
        'schedule_at',
    ];

    protected $casts = [
        'target_data' => 'array',
        'is_sent' => 'boolean',
        'schedule_at' => 'datetime',
    ];
}
