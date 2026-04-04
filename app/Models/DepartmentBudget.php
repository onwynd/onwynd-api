<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Budget approval state machine:
 *
 *   draft ──▶ pending_coo ──▶ pending_ceo ──▶ pending_finance ──▶ approved
 *     │              │               │                │
 *     │              │           queried ◀────────────┘ (CEO queries creator)
 *     │              │               │
 *     │              │       [creator responds]
 *     │              │               │
 *     │              │        pending_ceo (CEO re-reviews)
 *     └──────────────┴───────────────┴────────────────────────── rejected
 */
class DepartmentBudget extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'department', 'category', 'title', 'description',
        'amount_requested', 'currency', 'period', 'status',
        'submitted_by', 'approved_by_coo', 'approved_by_ceo', 'approved_by_finance', 'rejected_by',
        'coo_notes', 'ceo_notes', 'finance_notes', 'rejection_reason',
        'ceo_query_notes', 'ceo_suggested_amount', 'creator_response', 'creator_responded_at',
        'submitted_at', 'coo_reviewed_at', 'ceo_reviewed_at', 'finance_reviewed_at', 'rejected_at',
    ];

    protected $casts = [
        'amount_requested'     => 'decimal:2',
        'ceo_suggested_amount' => 'decimal:2',
        'submitted_at'         => 'datetime',
        'coo_reviewed_at'      => 'datetime',
        'ceo_reviewed_at'      => 'datetime',
        'finance_reviewed_at'  => 'datetime',
        'rejected_at'          => 'datetime',
        'creator_responded_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedByCoo()
    {
        return $this->belongsTo(User::class, 'approved_by_coo');
    }

    public function approvedByCeo()
    {
        return $this->belongsTo(User::class, 'approved_by_ceo');
    }

    public function approvedByFinance()
    {
        return $this->belongsTo(User::class, 'approved_by_finance');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function expenses()
    {
        return $this->hasMany(CampaignExpense::class);
    }

    // ── State transitions ──────────────────────────────────────────────────────

    public function submit(User $user): void
    {
        $this->update([
            'status'       => 'pending_coo',
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);
    }

    public function approveByCoo(User $user, ?string $notes = null): void
    {
        $this->update([
            'status'          => 'pending_ceo',
            'approved_by_coo' => $user->id,
            'coo_notes'       => $notes,
            'coo_reviewed_at' => now(),
        ]);
    }

    public function approveByCeo(User $user, ?string $notes = null): void
    {
        $this->update([
            'status'          => 'pending_finance',
            'approved_by_ceo' => $user->id,
            'ceo_notes'       => $notes,
            'ceo_reviewed_at' => now(),
        ]);
    }

    public function approveByFinance(User $user, ?string $notes = null): void
    {
        $this->update([
            'status'              => 'approved',
            'approved_by_finance' => $user->id,
            'finance_notes'       => $notes,
            'finance_reviewed_at' => now(),
        ]);
    }

    public function reject(User $user, string $reason): void
    {
        $this->update([
            'status'           => 'rejected',
            'rejected_by'      => $user->id,
            'rejection_reason' => $reason,
            'rejected_at'      => now(),
        ]);
    }

    /**
     * CEO queries the budget back to the creator with an optional suggested amount.
     * Status: pending_ceo → queried
     */
    public function queryCeo(User $ceo, string $notes, ?float $suggestedAmount = null): void
    {
        $this->update([
            'status'               => 'queried',
            'ceo_query_notes'      => $notes,
            'ceo_suggested_amount' => $suggestedAmount,
            'ceo_reviewed_at'      => now(),
            // Clear any previous creator response when re-queried
            'creator_response'     => null,
            'creator_responded_at' => null,
        ]);
    }

    /**
     * Original submitter responds to a CEO query.
     * Status: queried → pending_ceo (puts it back in the CEO queue)
     */
    public function respondToQuery(string $response): void
    {
        $this->update([
            'status'               => 'pending_ceo',
            'creator_response'     => $response,
            'creator_responded_at' => now(),
        ]);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopePendingCoo($query)
    {
        return $query->where('status', 'pending_coo');
    }

    public function scopePendingCeo($query)
    {
        return $query->where('status', 'pending_ceo');
    }

    public function scopePendingFinance($query)
    {
        return $query->where('status', 'pending_finance');
    }

    public function scopeForDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }
}
