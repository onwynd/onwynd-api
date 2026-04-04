<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'event_date',
        'audience',
        'description',
        'template_html',
        'active',
    ];

    protected $casts = [
        'event_date' => 'date',
        'audience' => 'array',
        'active' => 'boolean',
    ];
}
