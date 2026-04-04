<?php

namespace App\Services\AI;

class AIModelSelector
{
    /**
     * Select the best model for a given task and provider
     */
    public function selectModel(string $provider, string $taskType): string
    {
        $config = config("ai.models.{$provider}", []);

        return match ($taskType) {
            'coding', 'complex_reasoning' => $config['complex'] ?? 'gpt-4-turbo',
            'chat', 'casual' => $config['chat'] ?? 'gpt-3.5-turbo',
            'summary' => $config['fast'] ?? 'gpt-3.5-turbo',
            default => $config['default'] ?? 'gpt-3.5-turbo',
        };
    }
}
