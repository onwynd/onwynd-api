<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'status',
        'budget',
        'start_date',
        'end_date',
        'metrics',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'metrics' => 'array',
    ];
}
