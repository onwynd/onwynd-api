<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService implements AIProviderInterface
{
    protected $apiKey;

    protected $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function chat(array $messages, array $config = []): string
    {
        $model = $config['model'] ?? 'gpt-4-turbo-preview';

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
                Log::error('OpenAI Error: '.$response->body());
                throw new \Exception('OpenAI API Error: '.$response->status());
            }

            return $response->json('choices.0.message.content') ?? '';
        } catch (\Exception $e) {
            Log::error('OpenAI Exception: '.$e->getMessage());
            throw $e;
        }
    }

    public function embed(string $text): array
    {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/embeddings", [
                'model' => 'text-embedding-3-small',
                'input' => $text,
            ]);

        return $response->json('data.0.embedding') ?? [];
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }
}
