<?php

namespace App\Console\Commands;

use App\Models\MoodLog;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\Push\MoodCheckInPush;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dispatch daily push notifications to users who:
 *  - Have an active push subscription
 *  - Have not logged a mood check-in today
 *
 * Usage:  php artisan push:send-daily-checkins
 * Schedule: daily at 09:00
 */
class SendPushNotifications extends Command
{
    protected $signature = 'push:send-daily-checkins';

    protected $description = 'Send daily mood check-in push notifications to subscribed users';

    public function handle(): int
    {
        $this->info('Sending daily mood check-in push notifications...');

        $today = Carbon::today();
        $subscribedIds = PushSubscription::distinct()->pluck('user_id');

        if ($subscribedIds->isEmpty()) {
            $this->info('No push subscriptions found.');

            return Command::SUCCESS;
        }

        $sent = 0;

        User::whereIn('id', $subscribedIds)
            ->whereNotNull('email_verified_at')
            ->where('is_active', true)
            ->chunk(100, function ($users) use ($today, &$sent) {
                foreach ($users as $user) {
                    $checkedInToday = MoodLog::where('user_id', $user->id)
                        ->whereDate('created_at', $today)
                        ->exists();

                    if ($checkedInToday) {
                        continue;
                    }

                    try {
                        $user->notify(new MoodCheckInPush);
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::error('MoodCheckIn push failed', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Done — {$sent} push notifications dispatched.");

        return Command::SUCCESS;
    }
}
