<?php

namespace App\Exceptions;

use Exception;

class AIProviderException extends Exception
{
    public static function connectionFailed(string $provider): self
    {
        return new self("Failed to connect to AI provider: {$provider}");
    }

    public static function quotaExceeded(string $provider): self
    {
        return new self("Quota exceeded for AI provider: {$provider}");
    }
}
