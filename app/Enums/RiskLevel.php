<?php

namespace App\Enums;

enum RiskLevel: string
{
    case LOW = 'low';
    case MODERATE = 'moderate';
    case HIGH = 'high';
    case SEVERE = 'severe';
    case CRITICAL = 'critical';

    public function color(): string
    {
        return match ($this) {
            self::LOW => 'green',
            self::MODERATE => 'yellow',
            self::HIGH => 'orange',
            self::SEVERE => 'red',
            self::CRITICAL => 'darkred',
        };
    }
}
