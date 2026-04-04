<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Assessment extends Model
{
    protected $fillable = [
        'uuid',
        'title',
        'slug',
        'description',
        'type',
        'total_questions',
        'scoring_method',
        'interpretation_guide',
        'is_active',
    ];

    protected $casts = [
        'scoring_method' => 'array',
        'interpretation_guide' => 'array',
        'is_active' => 'boolean',
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

    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class);
    }
}
