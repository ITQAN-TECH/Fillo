<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'ar_about_us',
        'en_about_us',
        'ar_terms_and_conditions',
        'en_terms_and_conditions',
        'ar_privacy_policy',
        'en_privacy_policy',
    ];
}
