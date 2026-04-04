<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PromotionalCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'code',
        'description',
        'type',
        'discount_value',
        'currency',
        'max_uses',
        'uses_count',
        'max_uses_per_user',
        'valid_from',
        'valid_until',
        'applies_to',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'valid_from'     => 'datetime',
        'valid_until'    => 'datetime',
        'is_active'      => 'boolean',
        'discount_value' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromotionalCodeUsage::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && now()->gt($this->valid_until);
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->uses_count >= $this->max_uses;
    }
}
