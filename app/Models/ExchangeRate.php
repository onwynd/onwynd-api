<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExchangeRate extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'base_currency',
        'target_currency',
        'rate',
        'inverse_rate',
        'source',
        'is_active',
        'last_updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rate' => 'decimal:10',
        'inverse_rate' => 'decimal:10',
        'is_active' => 'boolean',
        'last_updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<string>
     */
    protected $dates = [
        'last_updated_at',
        'deleted_at',
    ];

    /**
     * Get the rate for a specific currency pair.
     */
    public static function getRate(string $baseCurrency, string $targetCurrency): ?self
    {
        return self::where('base_currency', strtoupper($baseCurrency))
            ->where('target_currency', strtoupper($targetCurrency))
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all active rates for a base currency.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getActiveRates(string $baseCurrency = 'NGN')
    {
        return self::where('base_currency', strtoupper($baseCurrency))
            ->where('is_active', true)
            ->orderBy('target_currency')
            ->get();
    }

    /**
     * Update or create a rate.
     */
    public static function updateRate(string $baseCurrency, string $targetCurrency, float $rate, string $source = 'manual'): self
    {
        $inverseRate = $rate > 0 ? 1 / $rate : 0;

        return self::updateOrCreate(
            [
                'base_currency' => strtoupper($baseCurrency),
                'target_currency' => strtoupper($targetCurrency),
            ],
            [
                'rate' => $rate,
                'inverse_rate' => $inverseRate,
                'source' => $source,
                'is_active' => true,
                'last_updated_at' => now(),
            ]
        );
    }

    /**
     * Deactivate a rate.
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Activate a rate.
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true, 'last_updated_at' => now()]);
    }

    /**
     * Scope to only active rates.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to rates updated within a specific timeframe.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUpdatedSince($query, \DateTimeInterface $since)
    {
        return $query->where('last_updated_at', '>=', $since);
    }

    /**
     * Get formatted rate for display.
     */
    public function getFormattedRateAttribute(): string
    {
        return number_format($this->rate, 6);
    }

    /**
     * Get formatted inverse rate for display.
     */
    public function getFormattedInverseRateAttribute(): string
    {
        return number_format($this->inverse_rate, 6);
    }
}
