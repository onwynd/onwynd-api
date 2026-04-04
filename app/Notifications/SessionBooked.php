<?php

namespace App\Notifications;

use App\Models\TherapySession;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;

class SessionBooked extends BaseNotification
{
    protected string $preferenceKey = 'appointment_reminders';

    public function __construct(
        protected TherapySession $session
    ) {}

    public function via($notifiable)
    {
        $channels = ['mail', 'database', 'fcm'];
        if (! empty($notifiable->phone)) {
            $channels[] = \App\Channels\WhatsAppChannel::class;
        }
        return $channels;
    }

    public function toWhatsApp(object $notifiable): string
    {
        $startTime = \Carbon\Carbon::parse($this->session->start_time)->format('l, F j, Y \a\t g:i A');
        $therapistName = $this->session->therapist->user->first_name . ' ' . $this->session->therapist->user->last_name;
        return "Hi {$notifiable->first_name}! Your therapy session with {$therapistName} is confirmed for {$startTime}. Log in to Onwynd to view details.";
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $startTime = Carbon::parse($this->session->start_time)->format('l, F j, Y \a\t g:i A');
        $therapistName = $this->session->therapist->user->full_name;

        return (new MailMessage)
            ->subject('Session Booked: '.$startTime)
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('Your therapy session with '.$therapistName.' has been successfully booked.')
            ->line('Time: '.$startTime)
            ->action('View Session Details', url('/dashboard/sessions/'.$this->session->uuid))
            ->line('We look forward to supporting you on your journey.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'session_booked',
            'title' => 'Session Booked',
            'message' => 'Your session on '.Carbon::parse($this->session->start_time)->format('M d, g:i A').' is confirmed.',
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
            'title' => 'Session Booked!',
            'body' => 'Your session with '.$this->session->therapist->user->full_name.' is confirmed.',
            'data' => [
                'type' => 'session_booked',
                'session_uuid' => $this->session->uuid,
            ],
        ];
    }
}
