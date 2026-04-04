<?php

namespace App\Models\Therapy;

use App\Models\Therapist;
use App\Traits\HasUUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapistClosedDate extends Model
{
    use HasUUID;

    /**
     * The table associated with the model.
     */
    protected $table = 'therapist_closed_dates';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'therapist_id',
        'start_date',
        'end_date',
        'reason',
        'notes',
        'is_recurring',
        'recurrence_pattern',
        'is_removed',
        'removed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_recurring' => 'boolean',
        'is_removed' => 'boolean',
        'removed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the therapist that owns the closed date.
     */
    public function therapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class);
    }

    /**
     * Check if closed date is currently active
     */
    public function isActive(): bool
    {
        return now()->between($this->start_date, $this->end_date)
            && ! $this->is_removed;
    }

    /**
     * Check if closed date has expired
     */
    public function hasExpired(): bool
    {
        return now()->isAfter($this->end_date);
    }

    /**
     * Check if closed date is upcoming
     */
    public function isUpcoming(): bool
    {
        return now()->isBefore($this->start_date)
            && ! $this->is_removed;
    }

    /**
     * Get duration in days
     */
    public function getDurationInDays(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Check if date falls within closed period
     */
    public function containsDate(\Carbon\Carbon $date): bool
    {
        return $date->between($this->start_date, $this->end_date)
            && ! $this->is_removed;
    }

    /**
     * Scope: Only active closed dates
     */
    public function scopeActive($query)
    {
        return $query->where('is_removed', false)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now());
    }

    /**
     * Scope: Only upcoming closed dates
     */
    public function scopeUpcoming($query)
    {
        return $query->where('is_removed', false)
            ->whereDate('start_date', '>', now());
    }

    /**
     * Scope: Only expired closed dates
     */
    public function scopeExpired($query)
    {
        return $query->where('is_removed', false)
            ->whereDate('end_date', '<', now());
    }

    /**
     * Scope: By therapist
     */
    public function scopeForTherapist($query, $therapistId)
    {
        return $query->where('therapist_id', $therapistId);
    }

    /**
     * Scope: By reason
     */
    public function scopeByReason($query, string $reason)
    {
        return $query->where('reason', $reason);
    }

    /**
     * Scope: Recurring closures
     */
    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    /**
     * Scope: Non-recurring closures
     */
    public function scopeNonRecurring($query)
    {
        return $query->where('is_recurring', false);
    }

    /**
     * Scope: Not removed (active/archived)
     */
    public function scopeNotRemoved($query)
    {
        return $query->where('is_removed', false);
    }

    /**
     * Scope: Removed (archived)
     */
    public function scopeRemoved($query)
    {
        return $query->where('is_removed', true);
    }
}
