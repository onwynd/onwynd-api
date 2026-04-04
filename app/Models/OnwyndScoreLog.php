<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnwyndScoreLog extends Model
{
    protected $fillable = [
        'user_id',
        'score',
        'breakdown',
        'logged_at',
    ];

    protected $casts = [
        'breakdown' => 'array',
        'logged_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
