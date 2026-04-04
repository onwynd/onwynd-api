<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;

class BookingPaymentFailed extends BaseNotification
{
    protected string $preferenceKey = 'billing_notifications';

    public function __construct(
        protected Payment $payment,
        protected string $reason = 'Payment declined'
    ) {}

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->payment->amount / 100, 2);
        $currency = strtoupper($this->payment->currency);

        return (new MailMessage)
            ->subject('Payment Failed: Session Booking')
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('Your payment for your session booking has failed.')
            ->line('Amount: '.$currency.' '.$amount)
            ->line('Reason: '.$this->reason)
            ->action('Retry Payment', url('/dashboard/booking/retry/'.$this->payment->reference))
            ->line('Please retry your payment to confirm your booking.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_failed',
            'title' => 'Payment Failed',
            'message' => 'Your payment for the session booking has failed: '.$this->reason,
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
            'reason' => $this->reason,
            'action_url' => '/dashboard/booking/retry/'.$this->payment->reference,
        ];
    }
}
