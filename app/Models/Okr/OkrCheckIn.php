<?php

namespace App\Models\Okr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OkrCheckIn extends Model
{
    protected $table = 'okr_check_ins';

    protected $fillable = [
        'key_result_id', 'value', 'note', 'is_automated', 'recorded_by', 'recorded_at',
    ];

    protected $casts = [
        'value'        => 'float',
        'is_automated' => 'boolean',
        'recorded_at'  => 'datetime',
    ];

    public function keyResult(): BelongsTo
    {
        return $this->belongsTo(OkrKeyResult::class, 'key_result_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
