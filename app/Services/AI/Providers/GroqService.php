<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService implements AIProviderInterface
{
    protected $apiKey;

    protected $baseUrl = 'https://api.groq.com/openai/v1';

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key');
    }

    public function getName(): string
    {
        return 'groq';
    }

    public function chat(array $messages, array $config = []): string
    {
        $model = $config['model'] ?? config('services.groq.model', 'llama-3.3-70b-versatile');

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(60)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $config['temperature'] ?? 0.7,
                    'max_tokens' => $config['max_tokens'] ?? 1000,
                ]);

            if ($response->failed()) {
                Log::error('Groq Error: '.$response->body());
                throw new \Exception('Groq API Error: '.$response->status());
            }

            return $response->json('choices.0.message.content') ?? '';
        } catch (\Exception $e) {
            Log::error('Groq Exception: '.$e->getMessage());
            throw $e;
        }
    }

    public function embed(string $text): array
    {
        // Groq doesn't support embeddings natively yet (or widely), usually fallbacks to OpenAI or others.
        // For now, we'll return empty or throw not supported.
        // Better to throw or return empty.
        return [];
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }
}
