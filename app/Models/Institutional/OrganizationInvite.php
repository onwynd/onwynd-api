<?php

namespace App\Models\Institutional;

use Illuminate\Database\Eloquent\Model;

class OrganizationInvite extends Model
{
    protected $fillable = [
        'organization_id',
        'email',
        'token',
        'role',
        'department',
        'expires_at',
        'accepted_at',
        'invited_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function invitedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return is_null($this->accepted_at) && $this->expires_at->isFuture();
    }
}
