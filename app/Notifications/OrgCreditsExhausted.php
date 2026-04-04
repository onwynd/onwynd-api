<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class OrgCreditsExhausted extends BaseNotification
{
    protected string $preferenceKey = 'billing_notifications';

    public $organization;

    public function __construct($organization)
    {
        $this->organization = $organization;
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Credits Exhausted: '.$this->organization->name)
            ->line('Your organization has exhausted its session credits.')
            ->line('Users will no longer be able to book sessions until credits are added.')
            ->action('Add Credits Now', url('/pricing/corporate'))
            ->line('Thank you for choosing Onwynd.');
    }

    public function toArray($notifiable)
    {
        return [
            'org_id' => $this->organization->id,
            'title' => 'Credits Exhausted',
            'message' => 'Your organization credits are empty.',
            'type' => 'org_credits_exhausted',
        ];
    }
}
