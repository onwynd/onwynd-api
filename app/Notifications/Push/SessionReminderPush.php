<?php

namespace App\Notifications\Push;

use App\Models\TherapySession;
use App\Services\PushNotification\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sends a browser push notification 15 minutes before a therapy session.
 * Used alongside the email session reminder.
 */
class SessionReminderPush extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly TherapySession $session) {}

    public function via($notifiable): array
    {
        return ['database']; // record in DB; push is dispatched separately via WebPushService
    }

    public function toArray($notifiable): array
    {
        $therapistName = $this->session->therapist?->user?->first_name ?? 'your therapist';
        $time = $this->session->scheduled_at
            ? \Carbon\Carbon::parse($this->session->scheduled_at)->format('h:i A')
            : 'soon';

        $payload = [
            'title' => 'Session in 15 minutes',
            'body' => "Your session with {$therapistName} starts at {$time}. Get ready!",
            'icon' => '/icons/icon-192x192.png',
            'tag' => 'session-reminder',
            'url' => config('app.url').'/sessions/'.$this->session->uuid,
        ];

        // Fire-and-forget push via WebPushService
        app(WebPushService::class)->sendToUser($notifiable, $payload);

        return [
            'type' => 'session_reminder',
            'session_id' => $this->session->uuid,
            'message' => $payload['body'],
        ];
    }
}
