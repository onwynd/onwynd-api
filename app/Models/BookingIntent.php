<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingIntent extends Model
{
    protected $fillable = [
        'user_id',
        'therapist_id',
        'context',
        'stage',
        'return_url',
        'therapist_name',
        'abandoned_email_sent_at',
        'completed_at',
    ];

    protected $casts = [
        'abandoned_email_sent_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function isAbandoned(): bool
    {
        return $this->completed_at === null
            && $this->created_at !== null
            && $this->created_at->lt(now()->subHours(2));
    }
}
