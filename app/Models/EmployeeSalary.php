<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalary extends Model
{
    protected $fillable = [
        'user_id',
        'base_salary',
        'currency',
        'role_label',
        'department',
        'effective_from',
        'effective_to',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'base_salary'    => 'decimal:2',
        'effective_from' => 'date',
        'effective_to'   => 'date',
    ];

    /** The employee this salary belongs to. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Who created / last updated this record. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Scope: only currently active salary records. */
    public function scopeActive($query)
    {
        return $query->whereNull('effective_to')
                     ->orWhere('effective_to', '>=', now()->toDateString());
    }

    /** Monthly salary total across all active employees. */
    public static function monthlyTotal(): float
    {
        return (float) static::active()->sum('base_salary');
    }
}
