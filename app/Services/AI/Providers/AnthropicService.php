<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicService implements AIProviderInterface
{
    protected $apiKey;

    protected $baseUrl = 'https://api.anthropic.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key');
    }

    public function getName(): string
    {
        return 'anthropic';
    }

    public function chat(array $messages, array $config = []): string
    {
        $model = $config['model'] ?? 'claude-3-opus-20240229';

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post("{$this->baseUrl}/messages", [
                'model' => $model,
                'messages' => $this->formatMessages($messages),
                'max_tokens' => $config['max_tokens'] ?? 1024,
                'temperature' => $config['temperature'] ?? 0.7,
            ]);

            if ($response->failed()) {
                Log::error('Anthropic Error: '.$response->body());
                throw new \Exception('Anthropic API Error');
            }

            return $response->json('content.0.text') ?? '';
        } catch (\Exception $e) {
            Log::error('Anthropic Exception: '.$e->getMessage());
            throw $e;
        }
    }

    public function embed(string $text): array
    {
        // Anthropic doesn't have a native embedding API yet (or typically used via VoyangeAI/etc)
        // Throw exception or fallback
        throw new \Exception('Embeddings not supported by Anthropic driver directly.');
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    protected function formatMessages(array $messages): array
    {
        // Anthropic requires specific role alternation or system prompt separation
        // This is a simplified mapper
        return array_map(function ($msg) {
            return [
                'role' => $msg['role'] === 'system' ? 'user' : $msg['role'], // System prompts handled differently usually
                'content' => $msg['content'],
            ];
        }, $messages);
    }
}
