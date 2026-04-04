<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ProfileIncomplete extends BaseNotification
{
    protected string $preferenceKey = 'wellbeing_checkins';

    public function __construct() {}

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Complete Your Profile')
            ->line('Your profile is missing important details.')
            ->line('A complete profile helps us match you with the best therapist for your needs.')
            ->action('Complete Profile Now', url('/profile'))
            ->line('Take a moment to finish setting up!');
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Complete Your Profile',
            'message' => 'Your profile is incomplete.',
            'type' => 'profile_incomplete',
        ];
    }
}
