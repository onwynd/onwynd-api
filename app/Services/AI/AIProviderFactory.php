<?php

namespace App\Services\AI;

use App\Services\AI\Providers\AnthropicService;
use App\Services\AI\Providers\CohereService;
use App\Services\AI\Providers\DeepSeekService;
use App\Services\AI\Providers\GoogleAIService;
use App\Services\AI\Providers\GroqService;
use App\Services\AI\Providers\OpenAIService;
use Exception;

class AIProviderFactory
{
    public function make(?string $provider = null): AIProviderInterface
    {
        $provider = $provider ?? config('services.ai.default', config('ai.default_provider', 'openai'));

        return match ($provider) {
            'openai' => new OpenAIService,
            'anthropic' => new AnthropicService,
            'google' => new GoogleAIService,
            'cohere' => new CohereService,
            'groq' => new GroqService,
            'deepseek' => new DeepSeekService,
            default => throw new Exception("Unknown AI Provider: {$provider}"),
        };
    }

    /**
     * Get the best provider based on task complexity
     */
    public function makeForTask(string $taskComplexity): AIProviderInterface
    {
        // Simple logic: High complexity -> Claude/GPT4, Low -> Gemini/GPT3.5
        $provider = match ($taskComplexity) {
            'high', 'complex' => config('ai.high_performance_provider', 'anthropic'),
            'low', 'simple' => config('ai.low_cost_provider', 'google'),
            default => config('ai.default_provider', 'openai'),
        };

        try {
            $service = $this->make($provider);
            if ($service->isAvailable()) {
                return $service;
            }
        } catch (Exception $e) {
            // Fallback
        }

        return $this->make('openai'); // Ultimate fallback
    }
}
