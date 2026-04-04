<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class MoodStreakAchieved extends BaseNotification
{
    protected string $preferenceKey = 'achievement_alerts';

    public function __construct(
        protected int $streakDays
    ) {}

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Congratulations! You\'ve hit a '.$this->streakDays.'-day mood streak!')
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('You have consistently tracked your mood for '.$this->streakDays.' days.')
            ->line('Your dedication to your mental wellness is inspiring.')
            ->action('View Your Progress', url('/dashboard/mood'))
            ->line('Keep it up — you\'re doing great!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mood_streak_achieved',
            'title' => 'Mood Streak Achieved!',
            'message' => 'Congratulations on your '.$this->streakDays.'-day mood tracking streak!',
            'streak_days' => $this->streakDays,
            'action_url' => '/dashboard/mood',
        ];
    }

    /**
     * Get the push representation of the notification (FCM).
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Mood Streak Achieved!',
            'body' => 'Congratulations on your '.$this->streakDays.'-day mood tracking streak!',
            'data' => [
                'type' => 'mood_streak_achieved',
                'streak_days' => $this->streakDays,
            ],
        ];
    }
}
