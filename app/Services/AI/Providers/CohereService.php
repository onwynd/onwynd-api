<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AIProviderInterface;

class CohereService implements AIProviderInterface
{
    protected $apiKey;

    protected $baseUrl = 'https://api.cohere.ai/v1';

    public function __construct()
    {
        $this->apiKey = config('services.cohere.api_key');
    }

    public function getName(): string
    {
        return 'cohere';
    }

    public function chat(array $messages, array $config = []): string
    {
        // Cohere implementation
        return 'Cohere response placeholder';
    }

    public function embed(string $text): array
    {
        return [];
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }
}
