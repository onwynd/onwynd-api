<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class WelcomeTherapist extends BaseNotification
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
            ->subject('Welcome to Onwynd: Therapist Dashboard!')
            ->greeting('Hello '.$this->user->first_name.'!')
            ->line('Welcome to Onwynd, your journey as a therapist on our platform starts here.')
            ->line('We are thrilled to have you as part of our provider network.')
            ->action('View Your Dashboard', url('/therapist/dashboard'))
            ->line('Let\'s begin your journey today!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'welcome_therapist',
            'title' => 'Welcome to Onwynd!',
            'message' => 'Your journey as a therapist on our platform starts here. We are thrilled to have you.',
            'action_url' => '/therapist/dashboard',
        ];
    }
}
