<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class JobPosting extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'title',
        'slug',
        'department',
        'location',
        'type',
        'salary_range',
        'experience_level',
        'description',
        'responsibilities',
        'qualifications',
        'benefits',
        'is_active',
        'status',
        'filled_at',
        'posted_at',
        'application_deadline',
        'max_applicants',
    ];

    protected $casts = [
        'responsibilities' => 'array',
        'qualifications' => 'array',
        'benefits' => 'array',
        'is_active' => 'boolean',
        'posted_at' => 'datetime',
        'filled_at' => 'datetime',
        'application_deadline' => 'datetime',
        'max_applicants' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title);
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('title') && empty($model->slug)) {
                $model->slug = Str::slug($model->title);
            }
        });
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Scope: jobs that should appear on the public listing.
     *  - status = 'open'  → visible unless past application_deadline
     *  - status = 'filled' → visible for 7 days after filled_at, then hidden
     *  - status = 'closed' → always hidden
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'open')
                ->where(function ($q2) {
                    // Show if no deadline set, or deadline is in the future
                    $q2->whereNull('application_deadline')
                        ->orWhere('application_deadline', '>=', now());
                })
                ->orWhere(function ($q2) {
                    $q2->where('status', 'filled')
                        ->where('filled_at', '>=', now()->subDays(7));
                });
        });
    }
}
