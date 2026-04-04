<?php

namespace App\Services\AI;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranscriptionService
{
    protected string $driver;

    protected string $apiKey;

    protected string $baseUrl;

    protected string $model;

    protected ?string $localUrl = null;

    public function __construct()
    {
        $this->driver = config('services.transcriber.driver', 'openai');
        if ($this->driver === 'local_whisper') {
            $this->localUrl = config('services.transcriber.local_whisper.url', env('LOCAL_WHISPER_URL', 'http://127.0.0.1:5001/transcribe'));
        } else {
            $this->apiKey = config('services.openai.api_key') ?? env('OPENAI_API_KEY', '');
            $this->baseUrl = 'https://api.openai.com/v1';
            $this->model = config('services.openai.whisper_model', 'whisper-1');
        }
    }

    public function isAvailable(): bool
    {
        if ($this->driver === 'local_whisper') {
            return ! empty($this->localUrl);
        }

        return ! empty($this->apiKey);
    }

    /**
     * Transcribe an audio file using OpenAI Whisper
     *
     * @param  array  $options  Optional options like 'prompt' or 'temperature'
     * @return array{text: string}
     */
    public function transcribe(UploadedFile $audioFile, array $options = []): array
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('Transcription provider not configured');
        }

        try {
            if ($this->driver === 'local_whisper') {
                $http = Http::asMultipart();
                if (! config('services.transcriber.verify', true)) {
                    $http = $http->withOptions(['verify' => false]);
                }
                $response = $http
                    ->attach('audio_file', file_get_contents($audioFile->getRealPath()), $audioFile->getClientOriginalName())
                    ->when(isset($options['prompt']), function ($http) use ($options) {
                        $http->attach('prompt', $options['prompt']);
                    })
                    ->post($this->localUrl);
            } else {
                $response = Http::withToken($this->apiKey)
                    ->asMultipart()
                    ->attach('file', file_get_contents($audioFile->getRealPath()), $audioFile->getClientOriginalName())
                    ->attach('model', $this->model)
                    ->when(isset($options['prompt']), function ($http) use ($options) {
                        $http->attach('prompt', $options['prompt']);
                    })
                    ->post("{$this->baseUrl}/audio/transcriptions");
            }

            if ($response->failed()) {
                Log::error('Transcription failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException('Failed to transcribe audio');
            }

            $text = $this->driver === 'local_whisper'
                ? ($response->json('data.text') ?? $response->json('text') ?? '')
                : ($response->json('text') ?? '');

            return ['text' => $text];
        } catch (\Throwable $e) {
            Log::error('Transcription exception', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
