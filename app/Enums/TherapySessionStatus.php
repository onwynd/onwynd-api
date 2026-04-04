<?php

namespace App\Enums;

class TherapySessionStatus
{
    const PENDING = 'pending';
    const PENDING_CONFIRMATION = 'pending_confirmation';
    const SCHEDULED = 'scheduled';
    const CONFIRMED = 'confirmed';
    const ONGOING = 'ongoing';
    const IN_PROGRESS = 'in_progress';
    const COMPLETED = 'completed';
    const CANCELLED = 'cancelled';
    const NO_SHOW = 'no_show';
    const REJECTED = 'rejected';
    const ENDED_EARLY = 'ended_early';

    // Statuses considered "active" (session can be joined)
    const ACTIVE_STATUSES = [self::SCHEDULED, self::CONFIRMED, self::ONGOING, self::IN_PROGRESS, self::PENDING_CONFIRMATION];

    // Statuses considered "past" (session is over)
    const TERMINAL_STATUSES = [self::COMPLETED, self::CANCELLED, self::NO_SHOW, self::REJECTED, self::ENDED_EARLY];

    // Statuses that allow cancellation
    const CANCELLABLE_STATUSES = [self::SCHEDULED, self::CONFIRMED, self::PENDING_CONFIRMATION];
}
