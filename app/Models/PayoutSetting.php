<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutSetting extends Model
{
    protected $fillable = [
        'role', 'payout_day', 'minimum_amount_kobo', 'currency',
        'provider', 'cycle_description', 'auto_process',
    ];

    protected $casts = [
        'payout_day'           => 'integer',
        'minimum_amount_kobo'  => 'integer',
        'auto_process'         => 'boolean',
    ];

    public function getMinimumAmountAttribute(): float
    {
        return $this->minimum_amount_kobo / 100;
    }
}
