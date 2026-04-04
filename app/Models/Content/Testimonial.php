<?php

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Testimonial extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'role',
        'avatar_url',
        'stats_text',
        'quote',
        'conversation_history',
        'rating',
        'is_active',
        'order',
    ];

    protected $casts = [
        'conversation_history' => 'array',
        'is_active' => 'boolean',
        'rating' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
