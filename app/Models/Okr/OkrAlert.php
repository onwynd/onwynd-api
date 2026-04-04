<?php

namespace App\Models\Okr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OkrAlert extends Model
{
    protected $table = 'okr_alerts';

    protected $fillable = [
        'key_result_id', 'alert_type', 'previous_health', 'new_health', 'notified_via',
    ];

    protected $casts = [
        'notified_via' => 'array',
    ];

    public function keyResult(): BelongsTo
    {
        return $this->belongsTo(OkrKeyResult::class, 'key_result_id');
    }
}
