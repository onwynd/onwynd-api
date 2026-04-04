<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CenterCheckIn extends Model
{
    protected $fillable = [
        'center_id',
        'patient_id',
        'staff_id',
        'check_in_time',
        'check_out_time',
        'service_type',
        'room_number',
        'vitals',
        'notes',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'vitals' => 'array',
    ];

    public function center()
    {
        return $this->belongsTo(PhysicalCenter::class, 'center_id');
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
