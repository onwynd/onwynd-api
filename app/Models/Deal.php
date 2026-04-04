<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'title',
        'value',
        'stage',
        'probability',
        'expected_close_date',
        'closed_at',
        'assigned_to',
        'owner_id',
        'closer_id',
        'lost_reason',
    ];

    protected $casts = [
        'expected_close_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closer_id');
    }
}
