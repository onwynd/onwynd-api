<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Designation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'code', 'level',
        'department_id', 'reports_to_designation_id',
        'salary_band_min', 'salary_band_max', 'currency',
        'description', 'is_active',
    ];

    protected $casts = [
        'level'           => 'integer',
        'salary_band_min' => 'decimal:2',
        'salary_band_max' => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function reportsTo()
    {
        return $this->belongsTo(Designation::class, 'reports_to_designation_id');
    }

    public function directReports()
    {
        return $this->hasMany(Designation::class, 'reports_to_designation_id');
    }

    public function employees()
    {
        return $this->hasMany(EmployeeRecord::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** True if this designation is more senior than another. */
    public function isMoreSeniorThan(Designation $other): bool
    {
        return $this->level < $other->level;
    }
}
