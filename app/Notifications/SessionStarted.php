<?php

namespace App\Notifications;

use App\Models\TherapySession;
use Illuminate\Notifications\Messages\MailMessage;

class SessionStarted extends BaseNotification
{
    protected string $preferenceKey = 'appointment_reminders';

    public function __construct(
        protected TherapySession $session
    ) {}

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $therapistName = $this->session->therapist->user->full_name;

        return (new MailMessage)
            ->subject('Your session with '.$therapistName.' is live!')
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('Your therapy session with '.$therapistName.' has started.')
            ->action('Join Session Now', url('/dashboard/sessions/'.$this->session->uuid))
            ->line('Please join the session as soon as possible.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'session_started',
            'title' => 'Session Live',
            'message' => 'Your session with '.$this->session->therapist->user->full_name.' is live now.',
            'session_id' => $this->session->id,
            'session_uuid' => $this->session->uuid,
            'action_url' => '/dashboard/sessions/'.$this->session->uuid,
        ];
    }

    /**
     * Get the push representation of the notification (FCM).
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Session Live',
            'body' => 'Your session with '.$this->session->therapist->user->full_name.' is live now.',
            'data' => [
                'type' => 'session_started',
                'session_uuid' => $this->session->uuid,
            ],
        ];
    }
}
