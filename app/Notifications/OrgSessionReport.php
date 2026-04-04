<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class OrgSessionReport extends BaseNotification
{
    protected string $preferenceKey = 'platform_updates';

    public $organization;

    public $reportPath;

    public function __construct($organization, $reportPath = null)
    {
        $this->organization = $organization;
        $this->reportPath = $reportPath;
    }

    public function toMail($notifiable)
    {
        $mail = (new MailMessage)
            ->subject('Weekly Session Report: '.$this->organization->name)
            ->line('Here is your weekly session report for '.$this->organization->name.'.')
            ->action('View Report Dashboard', url('/institutional/reports'));

        if ($this->reportPath) {
            $mail->attach($this->reportPath);
        }

        return $mail;
    }

    public function toArray($notifiable)
    {
        return [
            'org_id' => $this->organization->id,
            'title' => 'Weekly Report Ready',
            'message' => 'Your organization report is available.',
            'type' => 'org_session_report',
        ];
    }
}
