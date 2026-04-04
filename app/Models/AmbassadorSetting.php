<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmbassadorSetting extends Model
{
    protected $table = 'ambassador_settings';

    protected $fillable = ['data'];

    protected $casts = [
        'data' => 'array',
    ];
}
