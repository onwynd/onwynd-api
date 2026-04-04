<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class JobApplication extends Model
{
    protected $fillable = [
        'uuid',
        'job_posting_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'location',
        'cover_letter',
        'resume_url',
        'linkedin_url',
        'portfolio_url',
        'experience',
        'status',
        'hr_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'experience' => 'array',
        'reviewed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
