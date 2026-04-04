<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Prescription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'doctor_id',
        'patient_id',
        'medication_name',
        'dosage',
        'frequency',
        'duration',
        'instructions',
        'notes',
        'status',
        'issued_at',
        'expires_at',
        'digital_signature',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MedicationLog::class);
    }
}
