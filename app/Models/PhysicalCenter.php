<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PhysicalCenter extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'country',
        'postal_code',
        'phone',
        'email',
        'manager_id',
        'capacity',
        'operating_hours',
        'services_offered',
        'is_active',
    ];

    protected $casts = [
        'operating_hours' => 'array',
        'services_offered' => 'array',
        'is_active' => 'boolean',
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

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function equipment()
    {
        return $this->hasMany(CenterEquipment::class, 'center_id');
    }

    public function checkIns()
    {
        return $this->hasMany(CenterCheckIn::class, 'center_id');
    }

    public function services()
    {
        return $this->hasMany(CenterService::class, 'center_id');
    }

    public function bookings()
    {
        return $this->hasMany(CenterServiceBooking::class, 'center_id');
    }
}
