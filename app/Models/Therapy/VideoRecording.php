<?php

namespace App\Models\Therapy;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VideoRecording extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'video_session_id',
        'storage_path',
        'storage_disk',
        'filename',
        'mime_type',
        'size_bytes',
        'duration_seconds',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function videoSession()
    {
        return $this->belongsTo(VideoSession::class);
    }
}
