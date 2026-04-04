<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class OrgWelcome extends BaseNotification
{
    protected string $preferenceKey = 'platform_updates';

    public $organization;

    public function __construct($organization)
    {
        $this->organization = $organization;
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Welcome to Onwynd for Organizations')
            ->line('Your organization "'.$this->organization->name.'" has been successfully registered.')
            ->action('Access Organization Dashboard', url('/institutional/dashboard'))
            ->line('We look forward to supporting your team\'s mental wellbeing.');
    }

    public function toArray($notifiable)
    {
        return [
            'org_id' => $this->organization->id,
            'title' => 'Welcome to Onwynd',
            'message' => 'Your organization is ready.',
            'type' => 'org_welcome',
        ];
    }
}
