<?php

namespace App\Services\AI;

interface AIProviderInterface
{
    /**
     * Generate a chat completion
     *
     * @param  array  $messages  Message history [['role' => 'user', 'content' => '...']]
     * @param  array  $config  Optional config overrides (temperature, max_tokens)
     * @return string The generated response
     */
    public function chat(array $messages, array $config = []): string;

    /**
     * Generate text embedding
     *
     * @return array Vector embedding
     */
    public function embed(string $text): array;

    /**
     * Check if provider is available/healthy
     */
    public function isAvailable(): bool;

    /**
     * Get the provider name
     */
    public function getName(): string;
}
