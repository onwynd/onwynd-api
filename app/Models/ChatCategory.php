<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatCategory extends Model
{
    protected $fillable = [
        'slug',
        'label',
        'icon',
        'count',
        'is_active',
        'sort_order',
    ];
}
