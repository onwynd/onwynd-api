<?php

namespace App\Notifications;

use App\Models\TherapySession;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;

class SessionReminder extends BaseNotification
{
    protected string $preferenceKey = 'appointment_reminders';

    public function __construct(
        protected TherapySession $session,
        protected string $timeFrame = '24 hours' // '24 hours', '1 hour', '15 minutes'
    ) {}

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $startTime = Carbon::parse($this->session->start_time)->format('g:i A');
        $therapistName = $this->session->therapist->user->full_name;

        return (new MailMessage)
            ->subject('Reminder: Your session starts in '.$this->timeFrame)
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('This is a reminder for your therapy session with '.$therapistName.' at '.$startTime.'.')
            ->action('Join Session', url('/dashboard/sessions/'.$this->session->uuid))
            ->line('Please ensure you have a stable internet connection and are in a quiet, private space.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'session_reminder',
            'title' => 'Session Reminder',
            'message' => 'Your session starts in '.$this->timeFrame.' at '.Carbon::parse($this->session->start_time)->format('g:i A'),
            'session_id' => $this->session->id,
            'session_uuid' => $this->session->uuid,
            'therapist_name' => $this->session->therapist->user->full_name,
            'action_url' => '/dashboard/sessions/'.$this->session->uuid,
        ];
    }

    /**
     * Get the push representation of the notification (FCM).
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Session Reminder',
            'body' => 'Your session starts in '.$this->timeFrame.' at '.Carbon::parse($this->session->start_time)->format('g:i A'),
            'data' => [
                'type' => 'session_reminder',
                'session_uuid' => $this->session->uuid,
            ],
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        $startTime = Carbon::parse($this->session->start_time)->format('g:i A');
        $therapistName = $this->session->therapist->user->first_name . ' ' . $this->session->therapist->user->last_name;
        return "Hi {$notifiable->first_name}! Reminder: your session with {$therapistName} starts in {$this->timeFrame} at {$startTime}. Join via your Onwynd dashboard.";
    }
}
