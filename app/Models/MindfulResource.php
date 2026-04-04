<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MindfulResource extends Model
{
    protected $fillable = [
        'resource_category_id',
        'title',
        'slug',
        'type',
        'content',
        'media_url',
        'thumbnail_url',
        'duration_seconds',
        'is_premium',
        'views_count',
        'status',
        'admin_note',
        'submitted_by',
    ];

    protected $casts = [
        'is_premium' => 'boolean',
        'duration_seconds' => 'integer',
        'views_count' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(ResourceCategory::class, 'resource_category_id');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
