<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CenterService extends Model
{
    protected $fillable = [
        'center_id',
        'service_name',
        'service_type',
        'description',
        'duration_minutes',
        'price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function center()
    {
        return $this->belongsTo(PhysicalCenter::class, 'center_id');
    }

    public function bookings()
    {
        return $this->hasMany(CenterServiceBooking::class, 'service_id');
    }
}
