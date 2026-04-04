<?php

namespace App\Http\Controllers\API\V1\AI;

use App\Http\Controllers\API\BaseController;
use App\Services\AI\TranscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TranscriptionController extends BaseController
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'audio_file' => 'required|file|mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,video/mp4,video/webm|max:20480',
            'duration_seconds' => 'nullable|numeric|min:0',
            'prompt' => 'nullable|string|max:500',
        ]);

        $file = $request->file('audio_file');
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('public/voice_notes', $filename);

        $publicUrl = Storage::url($path);

        $transcribedText = '';
        try {
            $svc = new TranscriptionService;
            if ($svc->isAvailable()) {
                $options = [];
                if (! empty($data['prompt'])) {
                    $options['prompt'] = $data['prompt'];
                }
                $result = $svc->transcribe($file, $options);
                $transcribedText = $result['text'] ?? '';
            } else {
                $transcribedText = '';
            }
        } catch (\Throwable $e) {
            // Log is handled in service; keep response graceful
            $transcribedText = '';
        }

        return $this->sendResponse([
            'text' => $transcribedText,
            'audio_url' => $publicUrl,
            'duration_seconds' => $data['duration_seconds'] ?? null,
        ], 'Audio received.');
    }
}
