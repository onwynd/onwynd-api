<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyTip extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tip',
        'category',
        'technique',
        'metadata',
        'is_active',
        'display_date',
        'usage_count',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'display_date' => 'date',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * Scope to get active tips.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get tips for a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('display_date', $date);
    }

    /**
     * Scope to get tips for today.
     */
    public function scopeForToday($query)
    {
        return $query->where('display_date', today());
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage()
    {
        $this->increment('usage_count');
    }
}
