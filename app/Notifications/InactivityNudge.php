<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class InactivityNudge extends BaseNotification
{
    protected string $preferenceKey = 'mood_check_ins';

    public function __construct() {}

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Thinking of you — check in with Doctor Onwynd?')
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('It\'s been a week since we last saw you.')
            ->line('Doctor Onwynd is always here to chat and listen.')
            ->action('Chat with Doctor Onwynd', url('/chat'))
            ->line('We\'re always here for you when you need a check-in.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inactivity_nudge',
            'title' => 'Thinking of you',
            'message' => 'It\'s been a week since your last check-in. Chat with Doctor Onwynd?',
            'action_url' => '/chat',
        ];
    }

    /**
     * Get the push representation of the notification (FCM).
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Thinking of you',
            'body' => 'It\'s been a week since your last check-in. Chat with Doctor Onwynd?',
            'data' => [
                'type' => 'inactivity_nudge',
            ],
        ];
    }
}
