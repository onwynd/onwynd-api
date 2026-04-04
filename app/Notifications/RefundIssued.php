<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class RefundIssued extends BaseNotification
{
    protected string $preferenceKey = 'email_notifications';

    public function __construct(
        protected float $amount,
        protected string $reason
    ) {}

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Refund Issued: '.$this->amount)
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('A refund has been issued to your account for the amount of '.$this->amount.'.')
            ->line('Reason: '.$this->reason)
            ->line('The amount should reflect in your account within 3-5 business days.')
            ->action('View Payment History', url('/dashboard/payments'))
            ->line('Thank you for choosing Onwynd.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'refund_issued',
            'title' => 'Refund Issued',
            'message' => 'A refund of '.$this->amount.' has been issued for: '.$this->reason,
            'amount' => $this->amount,
            'action_url' => '/dashboard/payments',
        ];
    }
}
