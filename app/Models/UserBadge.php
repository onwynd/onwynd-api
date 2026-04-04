<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserBadge extends Pivot
{
    protected $table = 'user_badges';

    protected $fillable = [
        'user_id',
        'badge_id',
        'awarded_at',
    ];

    protected $casts = [
        'awarded_at' => 'datetime',
    ];
}
