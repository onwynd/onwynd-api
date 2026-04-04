<?php

namespace App\Services\Therapy;

use App\Models\Therapy\VideoRecording;
use App\Models\Therapy\VideoSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VideoRecordingService
{
    protected $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default', 'local');
    }

    /**
     * Store a recording chunk or full file
     */
    public function storeRecording(VideoSession $session, UploadedFile $file, array $metadata = [])
    {
        // Use therapy_session_id if available for better grouping, fallback to video session id
        $sessionId = $session->therapy_session_id ?? $session->id;
        $filename = 'documents/sessions/'.$sessionId.'/recordings/'.time().'_'.$file->getClientOriginalName();

        $path = $file->storeAs('', $filename, $this->disk);

        return VideoRecording::create([
            'video_session_id' => $session->id,
            'storage_path' => $path,
            'storage_disk' => $this->disk,
            'filename' => basename($path),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'status' => 'completed',
            'metadata' => $metadata,
        ]);
    }

    /**
     * Generate signed URL for playback
     */
    public function getPlaybackUrl(VideoRecording $recording)
    {
        return Storage::disk($recording->storage_disk)
            ->temporaryUrl($recording->storage_path, now()->addMinutes(60));
    }
}
