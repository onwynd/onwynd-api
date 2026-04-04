<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class DistressFlagRaised extends BaseNotification
{
    /**
     * Mandatory: clinical advisors must always receive distress alerts
     * regardless of their notification preference settings.
     * User opt-out of this notification would be a clinical safety risk.
     */
    protected bool $mandatory = true;

    protected array $mandatoryChannels = ['database', 'mail'];

    public function __construct(
        protected User $user,
        protected string $message,
        protected string $severity = 'high'
    ) {}

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $_notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('URGENT: Distress Flag Raised')
            ->greeting('Hello Clinical Advisor,')
            ->line('A user you oversee may need immediate attention.')
            ->line('A distress flag was raised due to recent AI companion interaction.')
            ->line('Severity: '.strtoupper($this->severity))
            ->line('Note: No private user data is included in this email for security.')
            ->action('View Clinical Dashboard', url('/clinical/dashboard'))
            ->line('Please review this user\'s status immediately.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'distress_flag_raised',
            'title' => 'URGENT: Distress Flag Raised',
            'message' => 'A user may need immediate attention. Severity: '.strtoupper($this->severity),
            'severity' => $this->severity,
            'action_url' => '/clinical/dashboard',
        ];
    }
}
