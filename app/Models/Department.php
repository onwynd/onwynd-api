<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'code', 'description',
        'head_user_id', 'parent_department_id',
        'headcount', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function head()
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_department_id');
    }

    /** Direct sub-departments (one level) */
    public function children()
    {
        return $this->hasMany(Department::class, 'parent_department_id');
    }

    /** All descendants (recursive eager load: ->with('children.children')) */
    public function allChildren()
    {
        return $this->hasMany(Department::class, 'parent_department_id')
                    ->with('allChildren');
    }

    public function designations()
    {
        return $this->hasMany(Designation::class);
    }

    public function employees()
    {
        return $this->hasMany(EmployeeRecord::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** Recalculate and persist headcount from live employee_records. */
    public function syncHeadcount(): void
    {
        $this->update(['headcount' => $this->employees()->count()]);
    }

    /** IDs of all users in this department (for notifications). */
    public function memberUserIds(): array
    {
        return $this->employees()->pluck('user_id')->toArray();
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_department_id');
    }
}
