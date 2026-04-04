<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class MoodNudgeNotification extends BaseNotification
{
    protected string $preferenceKey = 'mood_check_ins';

    public function __construct() {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('How are you feeling today? 🌿')
            ->greeting('Hey '.$notifiable->first_name.'!')
            ->line('It\'s been a couple of days since your last mood log.')
            ->line('Taking a moment to check in with yourself can make a real difference.')
            ->action('Log My Mood', config('app.frontend_url').'/mood')
            ->line('Your mental health matters — even on the ordinary days.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mood_nudge',
            'title' => 'How are you feeling today?',
            'message' => 'It\'s been a while since your last mood log. Take a moment to check in.',
            'action_url' => '/mood',
        ];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'How are you feeling today? 🌿',
            'body' => 'It\'s been a while since your last mood log. Take a moment to check in.',
            'data' => [
                'type' => 'mood_nudge',
                'action_url' => '/mood',
            ],
        ];
    }
}
