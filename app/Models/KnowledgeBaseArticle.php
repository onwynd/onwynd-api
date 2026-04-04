<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KnowledgeBaseArticle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'summary',
        'category_id',
        'author_id',
        'status',
        'visibility',
        'tags',
        'metadata',
        'views',
        'helpful_count',
        'not_helpful_count',
        'published_at',
    ];

    protected $casts = [
        'tags' => 'json',
        'metadata' => 'json',
        'published_at' => 'datetime',
        'views' => 'integer',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title);
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('title') && empty($model->slug)) {
                $model->slug = Str::slug($model->title);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopeForCorporate($query)
    {
        return $query->whereIn('visibility', ['public', 'corporate']);
    }

    public function scopeInternal($query)
    {
        return $query->whereIn('visibility', ['public', 'internal']); // Or just internal? Usually internal implies inclusive of public too
    }
}
