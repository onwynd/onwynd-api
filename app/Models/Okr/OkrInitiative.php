<?php

namespace App\Models\Okr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OkrInitiative extends Model
{
    protected $table = 'okr_initiatives';

    protected $fillable = [
        'key_result_id', 'title', 'description', 'status', 'owner_id', 'due_date',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function keyResult(): BelongsTo
    {
        return $this->belongsTo(OkrKeyResult::class, 'key_result_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
