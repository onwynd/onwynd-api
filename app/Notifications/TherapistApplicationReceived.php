<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class TherapistApplicationReceived extends BaseNotification
{
    protected string $preferenceKey = 'platform_updates';

    public function __construct() {}

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Therapist Application Received')
            ->line('We have received your application to become a therapist on Onwynd.')
            ->line('Our clinical board will review your credentials and get back to you within 5-7 business days.')
            ->line('Thank you for your interest in joining Onwynd!');
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Application Received',
            'message' => 'We are reviewing your therapist application.',
            'type' => 'therapist_application_received',
        ];
    }
}
