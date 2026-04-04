<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class WellbeingCheckin48h extends BaseNotification
{
    protected string $preferenceKey = 'mood_check_ins';

    public function __construct() {}

    public function via($notifiable)
    {
        return ['database', 'fcm'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('How have you been since your last session?')
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('It\'s been 48 hours since your last therapy session.')
            ->line('We want to check in and see how you are feeling.')
            ->action('Log Your Mood', url('/dashboard/mood'))
            ->line('Tracking your progress helps us support you better.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'wellbeing_checkin',
            'title' => 'Wellbeing Check-in',
            'message' => 'How have you been since your last session? Take a moment to check in.',
            'action_url' => '/dashboard/mood',
        ];
    }

    /**
     * Get the push representation of the notification (FCM).
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Wellbeing Check-in',
            'body' => 'How have you been since your last session? Take a moment to check in.',
            'data' => [
                'type' => 'wellbeing_checkin',
            ],
        ];
    }
}
