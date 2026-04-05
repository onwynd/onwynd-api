<?php

namespace App\Models\Gamification;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'category',
        'criteria_type',
        'criteria_value',
        'points',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'user_badges')->withTimestamps();
    }
}
