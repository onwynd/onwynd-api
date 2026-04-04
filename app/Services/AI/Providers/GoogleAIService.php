<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAIService implements AIProviderInterface
{
    protected $apiKey;

    protected $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.google.api_key');
    }

    public function getName(): string
    {
        return 'google';
    }

    public function chat(array $messages, array $config = []): string
    {
        $model = $config['model'] ?? 'gemini-pro';

        try {
            $contents = array_map(function ($msg) {
                return [
                    'role' => $msg['role'] === 'user' ? 'user' : 'model',
                    'parts' => [['text' => $msg['content']]],
                ];
            }, $messages);

            $response = Http::timeout(60)
                ->post("{$this->baseUrl}/{$model}:generateContent?key={$this->apiKey}", [
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => $config['temperature'] ?? 0.7,
                        'maxOutputTokens' => $config['max_tokens'] ?? 1000,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('Google AI Error: '.$response->body());
                throw new \Exception('Google AI API Error');
            }

            return $response->json('candidates.0.content.parts.0.text') ?? '';
        } catch (\Exception $e) {
            Log::error('Google AI Exception: '.$e->getMessage());
            throw $e;
        }
    }

    public function embed(string $text): array
    {
        // Implementation for embedding-001
        return [];
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }
}
