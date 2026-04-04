<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekService implements AIProviderInterface
{
    protected $apiKey;

    protected $baseUrl = 'https://api.deepseek.com';

    public function __construct()
    {
        $this->apiKey = config('services.deepseek.api_key');
    }

    public function getName(): string
    {
        return 'deepseek';
    }

    public function chat(array $messages, array $config = []): string
    {
        $model = $config['model'] ?? config('services.deepseek.model', 'deepseek-chat');

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
                Log::error('DeepSeek Error: '.$response->body());
                throw new \Exception('DeepSeek API Error: '.$response->status());
            }

            return $response->json('choices.0.message.content') ?? '';
        } catch (\Exception $e) {
            Log::error('DeepSeek Exception: '.$e->getMessage());
            throw $e;
        }
    }

    public function embed(string $text): array
    {
        // DeepSeek might not have embeddings endpoint exposed in the same way or used here.
        return [];
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }
}
