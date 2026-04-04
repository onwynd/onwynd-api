<?php

namespace App\Notifications;

use App\Models\TherapySession;
use Illuminate\Notifications\Messages\MailMessage;

class SessionNoShowPatient extends BaseNotification
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
            ->subject('We missed you in your session today')
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('We noticed you were unable to join your session with '.$therapistName.' today.')
            ->line('Life happens, and we want to ensure you get the support you need.')
            ->action('Rebook Session', url('/dashboard/therapists/'.$this->session->therapist->user->slug))
            ->line('Please note our 24-hour cancellation policy for future bookings.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'session_no_show_patient',
            'title' => 'Session Missed',
            'message' => 'We missed you in your session today with '.$this->session->therapist->user->full_name.'.',
            'session_id' => $this->session->id,
            'session_uuid' => $this->session->uuid,
            'action_url' => '/dashboard/therapists/'.$this->session->therapist->user->slug,
        ];
    }

    /**
     * Get the push representation of the notification (FCM).
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Session Missed',
            'body' => 'We missed you in your session today with '.$this->session->therapist->user->full_name.'.',
            'data' => [
                'type' => 'session_no_show_patient',
                'session_uuid' => $this->session->uuid,
            ],
        ];
    }
}
