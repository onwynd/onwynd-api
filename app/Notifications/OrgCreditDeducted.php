<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class OrgCreditDeducted extends BaseNotification
{
    protected string $preferenceKey = 'email_notifications';

    public function __construct(
        protected string $orgName,
        protected int $remainingCredits
    ) {}

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Organisation Credit Used: 1 Session')
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line('One session credit has been used by a member of your organisation, '.$this->orgName.'.')
            ->line('Remaining credits: '.$this->remainingCredits)
            ->action('View Organisation Dashboard', url('/corporate/dashboard'))
            ->line('Thank you for providing mental health support to your team.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'org_credit_deducted',
            'title' => 'Organisation Credit Used',
            'message' => 'One session credit has been used. Remaining: '.$this->remainingCredits,
            'remaining_credits' => $this->remainingCredits,
            'action_url' => '/corporate/dashboard',
        ];
    }
}
