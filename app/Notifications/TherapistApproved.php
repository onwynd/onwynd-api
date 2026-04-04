<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class TherapistApproved extends BaseNotification
{
    protected string $preferenceKey = 'platform_updates';

    public function __construct() {}

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Congratulations! You are Approved')
            ->line('Your application to join Onwynd has been approved.')
            ->action('Log In and Complete Profile', url('/therapist/profile'))
            ->line('We are excited to have you on board!');
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Application Approved',
            'message' => 'Your therapist application was approved.',
            'type' => 'therapist_approved',
        ];
    }
}
