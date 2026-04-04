<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EditorialCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(EditorialCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(EditorialCategory::class, 'parent_id');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(EditorialPost::class, 'editorial_post_category', 'category_id', 'editorial_post_id');
    }
}
