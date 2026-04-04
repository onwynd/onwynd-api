<?php

namespace App\Notifications;

use App\Models\TherapySession;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;

class SessionRescheduled extends BaseNotification
{
    protected string $preferenceKey = 'appointment_reminders';

    public function __construct(
        protected TherapySession $session,
        protected string $newTime
    ) {}

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $therapistName = $this->session->therapist->user->full_name;
        $formattedTime = Carbon::parse($this->newTime)->format('g:i A on F jS, Y');

        return (new MailMessage)
            ->subject('New Time Confirmed: Session Rescheduled')
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('Your therapy session with '.$therapistName.' has been rescheduled to '.$formattedTime.'.')
            ->action('View Session Details', url('/dashboard/sessions/'.$this->session->uuid))
            ->line('We look forward to seeing you at your new time.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'session_rescheduled',
            'title' => 'Session Rescheduled',
            'message' => 'Your session with '.$this->session->therapist->user->full_name.' is now at '.Carbon::parse($this->newTime)->format('g:i A on F jS').'.',
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
            'title' => 'Session Rescheduled',
            'body' => 'Your session with '.$this->session->therapist->user->full_name.' is now at '.Carbon::parse($this->newTime)->format('g:i A on F jS').'.',
            'data' => [
                'type' => 'session_rescheduled',
                'session_uuid' => $this->session->uuid,
            ],
        ];
    }
}
