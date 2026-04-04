<?php

namespace App\Notifications;

use App\Models\TherapySession;
use Illuminate\Notifications\Messages\MailMessage;

class SessionCompleted extends BaseNotification
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
            ->subject('How was your session with '.$therapistName.'?')
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('Your therapy session with '.$therapistName.' has ended.')
            ->line('We would love to know how it went and how you are feeling.')
            ->action('Rate Session', url('/dashboard/sessions/'.$this->session->uuid.'/rate'))
            ->line('Your feedback helps us provide better care for you.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'session_completed',
            'title' => 'Session Completed',
            'message' => 'Your session with '.$this->session->therapist->user->full_name.' has ended. Rate your experience!',
            'session_id' => $this->session->id,
            'session_uuid' => $this->session->uuid,
            'action_url' => '/dashboard/sessions/'.$this->session->uuid.'/rate',
        ];
    }

    /**
     * Get the push representation of the notification (FCM).
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Session Completed',
            'body' => 'Your session with '.$this->session->therapist->user->full_name.' has ended. Rate your experience!',
            'data' => [
                'type' => 'session_completed',
                'session_uuid' => $this->session->uuid,
            ],
        ];
    }
}
