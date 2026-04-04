<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignExpense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'marketing_campaign_id', 'submitted_by', 'department_budget_id',
        'platform', 'description',
        'amount_planned', 'amount_spent', 'currency', 'spend_date',
        'proof_file_path', 'proof_file_name', 'proof_file_type',
        'social_proof_url',
        'status', 'reviewed_by', 'review_notes', 'reviewed_at',
    ];

    protected $casts = [
        'amount_planned'   => 'decimal:2',
        'amount_spent'     => 'decimal:2',
        'balance_remaining' => 'decimal:2',
        'overspend_amount' => 'decimal:2',
        'is_overspend'     => 'boolean',
        'spend_date'       => 'date',
        'reviewed_at'      => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(MarketingCampaign::class, 'marketing_campaign_id');
    }

    public function budget()
    {
        return $this->belongsTo(DepartmentBudget::class, 'department_budget_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
