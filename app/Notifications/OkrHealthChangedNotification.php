<?php

namespace App\Notifications;

use App\Models\Okr\OkrKeyResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OkrHealthChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly OkrKeyResult $kr,
        private readonly string $oldHealth,
        private readonly string $newHealth,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];
        // Add FCM push if the user has push notifications enabled
        if (method_exists($notifiable, 'notificationSetting')
            && $notifiable->notificationSetting?->push_notifications) {
            $channels[] = 'fcm';
        }
        return $channels;
    }

    /**
     * Get the push representation of the notification (FCM).
     */
    public function toFcm(object $notifiable): array
    {
        $emoji = match ($this->newHealth) {
            'on_track'  => '🟢',
            'at_risk'   => '🟡',
            'off_track' => '🔴',
            default     => '⚪',
        };
        $statusLabel = ucwords(str_replace('_', ' ', $this->newHealth));

        return [
            'title' => "{$emoji} OKR Health Change",
            'body'  => "\"{$this->kr->title}\" is now {$statusLabel}",
            'data'  => [
                'type'       => 'okr_health_changed',
                'kr_id'      => (string) $this->kr->id,
                'new_health' => $this->newHealth,
                'action_url' => '/okr',
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $emoji = match ($this->newHealth) {
            'on_track'  => '🟢',
            'at_risk'   => '🟡',
            'off_track' => '🔴',
            default     => '⚪',
        };

        $statusLabel = str_replace('_', ' ', $this->newHealth);
        $subject     = "{$emoji} OKR Alert: \"{$this->kr->title}\" is now {$statusLabel}";

        $kr = $this->kr->loadMissing('objective');

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.okr.health-alert', [
                'kr'          => $kr,
                'oldHealth'   => $this->oldHealth,
                'newHealth'   => $this->newHealth,
                'progress'    => $kr->progress,
                'notifiable'  => $notifiable,
                'isTransactional' => true,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'okr_health_changed',
            'kr_id'      => $this->kr->id,
            'title'      => "OKR Health Alert: {$this->kr->title}",
            'message'    => 'Status changed from ' . str_replace('_', ' ', $this->oldHealth)
                          . ' to ' . str_replace('_', ' ', $this->newHealth)
                          . '. Progress: ' . round($this->kr->progress, 1) . '%',
            'old_health' => $this->oldHealth,
            'new_health' => $this->newHealth,
            'progress'   => round($this->kr->progress, 1),
            'action_url' => '/okr',
        ];
    }
}
