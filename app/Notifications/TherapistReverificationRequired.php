<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class TherapistReverificationRequired extends BaseNotification
{
    protected string $preferenceKey = 'email_notifications';

    /**
     * Mark as mandatory so the notification is always delivered regardless of
     * individual notification preferences — re-verification is account-critical.
     */
    protected bool $mandatory = true;

    protected array $mandatoryChannels = ['mail', 'database'];

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $name = trim(($notifiable->first_name ?? '') . ' ' . ($notifiable->last_name ?? ''));
        $greeting = $name ? "Hello {$name}" : 'Hello';

        return (new MailMessage)
            ->subject('Action Required: Re-verify Your Practice Location')
            ->greeting($greeting)
            ->line('Our system has detected login activity from a location that differs from your registered country of practice.')
            ->line('To keep your account secure and ensure your profile information is accurate, we need you to re-verify your location and practice details.')
            ->action('Re-verify My Account', url('/therapist/settings?tab=verification'))
            ->line('If you believe this is an error or you have recently moved your practice, please contact our support team and we will assist you promptly.')
            ->line('If you did not attempt to log in from an unexpected location, please secure your account immediately by changing your password.')
            ->salutation('The Onwynd Team');
    }

    /**
     * Build the database (in-app) representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type'    => 'reverification_required',
            'title'   => 'Re-verification Required',
            'message' => 'Your account requires re-verification due to a location mismatch. Please review your practice details.',
            'action'  => '/therapist/settings?tab=verification',
        ];
    }
}
