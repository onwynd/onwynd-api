<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'physical_center_id',
        'service_id', // Nullable if generic booking
        'booking_reference',
        'scheduled_at',
        'status', // 'confirmed', 'pending', 'cancelled', 'completed'
        'notes',
        'total_price',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'total_price' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(PhysicalCenter::class, 'physical_center_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(CenterService::class, 'service_id');
    }
}
