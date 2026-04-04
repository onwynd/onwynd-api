<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The preference key in notification_settings table.
     */
    protected string $preferenceKey = 'email_notifications';

    /**
     * Mandatory notifications bypass user preference settings entirely.
     *
     * Set $mandatory = true on safety-critical notifications where user opt-out
     * would create a clinical safety risk (e.g. DistressFlagRaised, clinical alerts).
     *
     * When true: via() uses $mandatoryChannels regardless of user notification prefs.
     * When false (default): via() respects user notification preferences.
     */
    protected bool $mandatory = false;

    /**
     * Channels used when $mandatory = true.
     * These channels are always sent, regardless of user preferences.
     */
    protected array $mandatoryChannels = ['database', 'mail'];

    /**
     * Get the notification's delivery channels.
     *
     * For mandatory notifications: always delivers on $mandatoryChannels.
     * For standard notifications: checks user notification preferences.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Safety-critical notifications: bypass user preferences entirely
        if ($this->mandatory) {
            return $this->mandatoryChannels;
        }

        if (! $notifiable instanceof User) {
            return ['database'];
        }

        $settings = $notifiable->notificationSetting;
        if (! $settings) {
            return ['database', 'email'];
        }

        $channels = ['database'];

        // Check if this specific notification type is enabled
        if (! $settings->{$this->preferenceKey}) {
            return ['database'];
        }

        // Add email if enabled globally and for this user
        if ($settings->email_notifications) {
            $channels[] = 'email';
        }

        // Add push if enabled globally and for this user
        if ($settings->push_notifications) {
            $channels[] = 'fcm';
        }

        // Add WhatsApp if user has a phone number and the notification implements toWhatsApp()
        if (! empty($notifiable->phone) && method_exists($this, 'toWhatsApp')) {
            $channels[] = \App\Channels\WhatsAppChannel::class;
        }

        return $channels;
    }
}
