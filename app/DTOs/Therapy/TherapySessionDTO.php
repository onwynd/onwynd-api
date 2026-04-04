<?php

namespace App\DTOs\Therapy;

use Carbon\Carbon;

class TherapySessionDTO
{
    public function __construct(
        public readonly string $patientId,
        public readonly string $therapistId,
        public readonly Carbon $scheduledAt,
        public readonly int $durationMinutes,
        public readonly string $type, // video, audio, chat
        public readonly ?string $notes = null
    ) {}
}
