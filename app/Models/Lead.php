<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'status',
        'source',
        'assigned_to',
        'owner_id',
        'notes',
        'handoff_note',
        'handed_off_at',
        'handed_off_by',
    ];

    protected $casts = [
        'handed_off_at' => 'datetime',
    ];

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function handedOffBy()
    {
        return $this->belongsTo(User::class, 'handed_off_by');
    }

    public function deals()
    {
        return $this->hasMany(Deal::class);
    }
}
