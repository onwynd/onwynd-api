<?php

namespace App\Models\Therapy;

use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AudioMetric extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected $casts = [
        'recorded_at' => 'datetime',
        'packet_loss_percent' => 'decimal:2',
    ];

    public function session()
    {
        return $this->belongsTo(TherapySession::class, 'therapy_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
