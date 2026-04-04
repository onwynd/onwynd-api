<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CenterEquipment extends Model
{
    protected $fillable = [
        'center_id',
        'equipment_type',
        'equipment_name',
        'serial_number',
        'status',
        'last_maintenance',
        'next_maintenance',
        'purchase_date',
        'warranty_expiry',
        'notes',
    ];

    protected $casts = [
        'last_maintenance' => 'date',
        'next_maintenance' => 'date',
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
    ];

    public function center()
    {
        return $this->belongsTo(PhysicalCenter::class, 'center_id');
    }
}
