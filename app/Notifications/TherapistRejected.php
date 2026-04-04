<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class TherapistRejected extends BaseNotification
{
    protected string $preferenceKey = 'platform_updates';

    public $reason;

    public function __construct($reason = null)
    {
        $this->reason = $reason;
    }

    public function toMail($notifiable)
    {
        $mail = (new MailMessage)
            ->subject('Update on Your Application')
            ->line('We regret to inform you that we cannot approve your application at this time.');

        if ($this->reason) {
            $mail->line('Reason: '.$this->reason);
        }

        return $mail;
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Application Status',
            'message' => 'Your therapist application was not approved.',
            'type' => 'therapist_rejected',
        ];
    }
}
