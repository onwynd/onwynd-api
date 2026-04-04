<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * users:mark-offline
 *
 * Runs every 5 minutes via the scheduler.
 * Sets is_online = false for any user whose last_seen_at is older than 30 minutes.
 *
 * This implements the backend half of the therapist heartbeat system (TODO-9).
 * The frontend calls POST /api/v1/me/heartbeat every 90 seconds to keep
 * last_seen_at fresh. Any user who stops sending heartbeats (tab closed,
 * session ended, device offline) will be marked offline within 30-35 minutes.
 *
 * For the Available-Now booking banner this means maximum 35-minute staleness,
 * which is acceptable for an MVP. A stricter window can be configured once
 * the heartbeat usage is validated in production.
 *
 * Schedule: ->everyFiveMinutes() in app/Console/Kernel.php
 */
class MarkOfflineInactiveUsers extends Command
{
    protected $signature = 'users:mark-offline';
    protected $description = 'Mark users offline if their last heartbeat was more than 30 minutes ago.';

    public function handle(): int
    {
        $cutoff  = now()->subMinutes(30);
        $updated = User::where('is_online', true)
            ->where('last_seen_at', '<', $cutoff)
            ->update(['is_online' => false]);

        $this->info("Marked {$updated} user(s) offline (last seen before {$cutoff}).");

        return Command::SUCCESS;
    }
}
