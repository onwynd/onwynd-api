<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class WelcomePatient extends BaseNotification
{
    protected string $preferenceKey = 'email_notifications';

    public function __construct(
        protected User $user
    ) {}

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to Onwynd!')
            ->greeting('Hello '.$this->user->first_name.'!')
            ->line('Welcome to Onwynd, your journey to better mental health starts here.')
            ->line('We are thrilled to have you as part of our community.')
            ->action('Get Started', url('/dashboard'))
            ->line('Let\'s begin your journey today!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'welcome_patient',
            'title' => 'Welcome to Onwynd!',
            'message' => 'Your journey to better mental health starts here. We are thrilled to have you.',
            'action_url' => '/dashboard',
        ];
    }
}
