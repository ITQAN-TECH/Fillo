<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = [
        'ar_question',
        'en_question',
        'ar_answer',
        'en_answer',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];
}
