<?php

namespace App\Notifications;

use App\Models\TherapySession;
use Illuminate\Notifications\Messages\MailMessage;

class SessionNoShowTherapist extends BaseNotification
{
    protected string $preferenceKey = 'appointment_reminders';

    public function __construct(
        protected TherapySession $session,
        protected string $promoCode = 'SORRY100'
    ) {}

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $therapistName = $this->session->therapist->user->full_name;

        return (new MailMessage)
            ->subject('Sincere apologies for your session today')
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('We are very sorry that your therapist, '.$therapistName.', was unable to join your session today.')
            ->line('A full refund has been issued to your account and should reflect within 3-5 business days.')
            ->line('To make up for this, we have provided a promo code for your next session: '.$this->promoCode)
            ->action('Rebook Another Therapist', url('/therapists'))
            ->line('We are committed to providing you with reliable care.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'session_no_show_therapist',
            'title' => 'Therapist No-Show',
            'message' => 'Our apologies — your therapist missed your session today. A full refund has been issued.',
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
            'title' => 'Therapist No-Show',
            'body' => 'Our apologies — your therapist missed your session today. A full refund has been issued.',
            'data' => [
                'type' => 'session_no_show_therapist',
                'session_uuid' => $this->session->uuid,
            ],
        ];
    }
}
