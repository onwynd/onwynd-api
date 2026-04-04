<?php

namespace App\Models\Therapy;

use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VideoSession extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'therapy_session_id',
        'host_id',
        'participant_id',
        'provider',
        'room_name',
        'therapist_token',
        'patient_token',
        'prepared_at',
        'status',
        'started_at',
        'ended_at',
        'duration_seconds',
        'quality_metrics',
        'disconnect_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'quality_metrics' => 'array',
        'host_id' => 'integer',
        'participant_id' => 'integer',
    ];

    public function therapySession()
    {
        return $this->belongsTo(TherapySession::class);
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function participant()
    {
        return $this->belongsTo(User::class, 'participant_id');
    }

    public function recordings()
    {
        return $this->hasMany(VideoRecording::class);
    }
}
