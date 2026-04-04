<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class OrgLowCredits extends BaseNotification
{
    protected string $preferenceKey = 'email_notifications';

    public function __construct(
        protected string $orgName,
        protected int $remainingCredits
    ) {}

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('URGENT: Low Session Credits for '.$this->orgName)
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line('Your organisation, '.$this->orgName.', has only '.$this->remainingCredits.' session credits remaining.')
            ->line('To ensure uninterrupted mental health support for your team, please top up your credits.')
            ->action('Top Up Credits', url('/corporate/dashboard/billing'))
            ->line('Thank you for providing mental health support to your team.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'org_low_credits',
            'title' => 'Low Credits Alert',
            'message' => 'Your organisation has only '.$this->remainingCredits.' session credits remaining.',
            'remaining_credits' => $this->remainingCredits,
            'action_url' => '/corporate/dashboard/billing',
        ];
    }
}
