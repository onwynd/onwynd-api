<?php

namespace App\Notifications;

use App\Models\TherapySession;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;

class SessionBookedTherapist extends BaseNotification
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
        $startTime = Carbon::parse($this->session->start_time)->format('l, F j, Y \a\t g:i A');
        $patientName = $this->session->user->display_name ?: $this->session->user->first_name;
        return "Hi {$notifiable->first_name}! A new session has been booked with you by {$patientName} for {$startTime}. Log in to Onwynd to prepare.";
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $startTime = Carbon::parse($this->session->start_time)->format('l, F j, Y \a\t g:i A');
        $patientName = $this->session->user->full_name;

        return (new MailMessage)
            ->subject('New Session Booked: '.$patientName)
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('A new session has been booked with you by '.$patientName.'.')
            ->line('Time: '.$startTime)
            ->action('View Session Details', url('/therapist/dashboard/sessions/'.$this->session->uuid))
            ->line('Please review the session details and prepare for your meeting.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_session_booked',
            'title' => 'New Session Booked',
            'message' => 'New session with '.$this->session->user->full_name.' on '.Carbon::parse($this->session->start_time)->format('M d, g:i A'),
            'session_id' => $this->session->id,
            'session_uuid' => $this->session->uuid,
            'patient_name' => $this->session->user->full_name,
            'action_url' => '/therapist/dashboard/sessions/'.$this->session->uuid,
        ];
    }

    /**
     * Get the push representation of the notification (FCM).
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'New Session Booked!',
            'body' => 'New session with '.$this->session->user->full_name.' on '.Carbon::parse($this->session->start_time)->format('M d, g:i A'),
            'data' => [
                'type' => 'new_session_booked',
                'session_uuid' => $this->session->uuid,
            ],
        ];
    }
}
