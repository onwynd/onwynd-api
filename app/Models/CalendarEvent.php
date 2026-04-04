<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    protected $fillable = [
        'title',
        'description',
        'start_time',
        'end_time',
        'type',
        'status',
        'lead_id',
        'created_by',
        'assigned_to',
        'participants',
        'meeting_link',
        'notes',
        'visible_to_roles',
    ];

    protected $casts = [
        'start_time'        => 'datetime',
        'end_time'          => 'datetime',
        'participants'      => 'array',
        'visible_to_roles'  => 'array',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
