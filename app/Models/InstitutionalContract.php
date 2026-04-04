<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionalContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_user_id',
        'company_name',
        'contract_type',
        'start_date',
        'end_date',
        'employee_count_limit',
        'total_sessions_quota',    // Total sessions allowed for the whole org
        'sessions_used',           // Total sessions used by the whole org
        'features_enabled',
        'contract_value',
        'status',
        'document_url',
        'midpoint_notified_at',    // Timestamped when 50% midpoint email was sent
        'pre_renewal_notified_at', // Timestamped when 14-day pre-renewal email was sent
        'expiry_notified_at',      // Timestamped when expiry email was sent
    ];

    protected $casts = [
        'start_date'              => 'date',
        'end_date'                => 'date',
        'total_sessions_quota'    => 'integer',
        'sessions_used'           => 'integer',
        'features_enabled'        => 'array',
        'contract_value'          => 'decimal:2',
        'midpoint_notified_at'    => 'datetime',
        'pre_renewal_notified_at' => 'datetime',
        'expiry_notified_at'      => 'datetime',
    ];

    public function institutionUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'institution_user_id');
    }
}
