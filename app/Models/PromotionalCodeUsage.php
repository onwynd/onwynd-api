<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionalCodeUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'promotional_code_id',
        'user_id',
        'session_id',
        'discount_applied',
    ];

    protected $casts = [
        'discount_applied' => 'decimal:2',
    ];

    public function promotionalCode(): BelongsTo
    {
        return $this->belongsTo(PromotionalCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TherapySession::class, 'session_id');
    }
}
