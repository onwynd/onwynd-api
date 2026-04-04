<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CenterServiceBooking extends Model
{
    protected $fillable = [
        'uuid',
        'center_id',
        'service_id',
        'patient_id',
        'therapist_id',
        'scheduled_at',
        'status',
        'room_number',
        'equipment_used',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'equipment_used' => 'array',
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

    public function center()
    {
        return $this->belongsTo(PhysicalCenter::class, 'center_id');
    }

    public function service()
    {
        return $this->belongsTo(CenterService::class, 'service_id');
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function therapist()
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }
}
