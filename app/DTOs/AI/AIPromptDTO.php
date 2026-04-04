<?php

namespace App\DTOs\AI;

class AIPromptDTO
{
    public function __construct(
        public readonly string $role,
        public readonly string $content,
        public readonly array $metadata = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            role: $data['role'],
            content: $data['content'],
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
            'metadata' => $this->metadata,
        ];
    }
}
