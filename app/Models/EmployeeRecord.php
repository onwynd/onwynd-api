<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * EmployeeRecord is the authoritative source of:
 *   - which department an employee belongs to
 *   - their designation (title + level)
 *   - their direct line manager
 *   - employment status and contract details
 *
 * Relationship summary:
 *
 *   User ──1:1──▶ EmployeeRecord
 *   EmployeeRecord ──N:1──▶ Department
 *   EmployeeRecord ──N:1──▶ Designation
 *   EmployeeRecord ──N:1──▶ User (manager)
 *   EmployeeRecord ──1:N──▶ EmployeeRecord (direct reports)
 */
class EmployeeRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'employee_number',
        'department_id', 'designation_id', 'manager_id',
        'join_date', 'probation_end_date', 'confirmation_date', 'exit_date',
        'contract_type', 'employment_status', 'work_mode', 'office_location',
        'current_salary', 'salary_currency',
        'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'join_date'           => 'date',
        'probation_end_date'  => 'date',
        'confirmation_date'   => 'date',
        'exit_date'           => 'date',
        'current_salary'      => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    /** Direct line manager (a User, not another EmployeeRecord) */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** Manager's EmployeeRecord (resolved through user → employee_record) */
    public function managerRecord()
    {
        return $this->hasOneThrough(
            EmployeeRecord::class,
            User::class,
            'id',            // users.id
            'user_id',       // employee_records.user_id
            'manager_id',    // this.manager_id
            'id',            // users.id
        );
    }

    /** All employees who report directly to this record's user */
    public function directReports()
    {
        return $this->hasMany(EmployeeRecord::class, 'manager_id', 'user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Resolve the approval chain for this employee:
     *   [direct_manager, department_head, hr_head, coo]
     * Returns a list of User objects in order of approval authority.
     */
    public function approvalChain(): array
    {
        $chain = [];

        // 1. Direct manager
        if ($this->manager_id && $this->manager) {
            $chain[] = $this->manager;
        }

        // 2. Department head (skip if they are also the direct manager)
        if ($this->department?->head_user_id
            && $this->department->head_user_id !== $this->manager_id) {
            $chain[] = $this->department->head;
        }

        return array_filter($chain);
    }

    /** Auto-generate a sequential employee number */
    public static function nextEmployeeNumber(): string
    {
        $year  = now()->year;
        $last  = static::withTrashed()
            ->where('employee_number', 'like', "ONW-{$year}-%")
            ->orderByDesc('id')
            ->value('employee_number');
        $seq   = $last ? ((int) substr($last, -4)) + 1 : 1;
        return sprintf('ONW-%d-%04d', $year, $seq);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereIn('employment_status', ['active', 'probation']);
    }

    public function scopeByDepartment($query, int $deptId)
    {
        return $query->where('department_id', $deptId);
    }
}
