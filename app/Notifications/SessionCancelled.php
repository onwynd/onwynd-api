<?php

namespace App\Notifications;

use App\Models\TherapySession;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;

class SessionCancelled extends BaseNotification
{
    protected string $preferenceKey = 'appointment_reminders';

    public function __construct(
        protected TherapySession $session,
        protected string $cancelledBy = 'patient' // 'patient' or 'therapist'
    ) {}

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $therapistName = $this->session->therapist->user->full_name;

        return (new MailMessage)
            ->subject('Session Cancellation Confirmed')
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('Your session with '.$therapistName.' has been cancelled.')
            ->line('You can rebook at any time that works for you.')
            ->action('Browse Therapists', url('/therapists'))
            ->line('We hope to see you back soon.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'session_cancelled',
            'title' => 'Session Cancelled',
            'message' => 'Your session with '.$this->session->therapist->user->full_name.' has been cancelled.',
            'session_id' => $this->session->id,
            'session_uuid' => $this->session->uuid,
            'action_url' => '/therapists',
        ];
    }

    /**
     * Get the push representation of the notification (FCM).
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Session Cancelled',
            'body' => 'Your session with '.$this->session->therapist->user->full_name.' has been cancelled.',
            'data' => [
                'type' => 'session_cancelled',
                'session_uuid' => $this->session->uuid,
            ],
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        $therapistName = $this->session->therapist->user->first_name . ' ' . $this->session->therapist->user->last_name;
        $scheduledTime = Carbon::parse($this->session->start_time)->format('l, F j \a\t g:i A');
        return "Hi {$notifiable->first_name}, your session with {$therapistName} scheduled for {$scheduledTime} has been cancelled. You can rebook anytime at onwynd.com.";
    }
}
