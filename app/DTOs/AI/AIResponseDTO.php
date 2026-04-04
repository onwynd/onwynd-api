<?php

namespace App\DTOs\AI;

class AIResponseDTO
{
    public function __construct(
        public readonly string $content,
        public readonly int $tokensUsed,
        public readonly string $model,
        public readonly string $finishReason
    ) {}
}
