<?php

namespace App\Notifications\Push;

use App\Services\PushNotification\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Daily mood check-in browser push reminder.
 * Sent at 09:00 local time to users who haven't checked in today.
 */
class MoodCheckInPush extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $firstName = $notifiable->first_name ?? 'there';

        $payload = [
            'title' => 'Good morning, '.$firstName.' 🌱',
            'body' => 'How are you feeling today? A 30-second check-in helps us support you better.',
            'icon' => '/icons/icon-192x192.png',
            'tag' => 'mood-checkin',
            'url' => config('app.url').'/mood',
        ];

        app(WebPushService::class)->sendToUser($notifiable, $payload);

        return [
            'type' => 'mood_checkin',
            'message' => $payload['body'],
        ];
    }
}
