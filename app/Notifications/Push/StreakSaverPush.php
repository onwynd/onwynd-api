<?php

namespace App\Notifications\Push;

use App\Services\PushNotification\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Browser push companion to the streak saver email.
 * Sent at 18:00 to users with a ≥5-day streak who haven't logged activity today.
 */
class StreakSaverPush extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $currentStreak,
        public readonly int $hoursLeft,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $payload = [
            'title' => "🔥 {$this->currentStreak}-day streak at risk",
            'body' => "Only {$this->hoursLeft}h left to save your streak! A quick 2-minute activity is all it takes.",
            'icon' => '/icons/icon-192x192.png',
            'tag' => 'streak-saver',
            'url' => config('app.url').'/unwind',
        ];

        app(WebPushService::class)->sendToUser($notifiable, $payload);

        return [
            'type' => 'streak_saver',
            'current_streak' => $this->currentStreak,
            'hours_left' => $this->hoursLeft,
            'message' => $payload['body'],
        ];
    }
}
