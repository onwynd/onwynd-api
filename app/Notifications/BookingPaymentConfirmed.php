<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;

class BookingPaymentConfirmed extends BaseNotification
{
    protected string $preferenceKey = 'billing_notifications';

    public function __construct(
        protected Payment $payment
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
        $amount = number_format($this->payment->amount / 100, 2);
        $currency = strtoupper($this->payment->currency);

        return (new MailMessage)
            ->subject('Payment Confirmed: Session Booking')
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line('Your payment for your session booking has been confirmed.')
            ->line('Amount: '.$currency.' '.$amount)
            ->line('Reference: '.$this->payment->reference)
            ->action('View Receipt', url('/dashboard/payments/'.$this->payment->id))
            ->line('Thank you for your payment.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_confirmed',
            'title' => 'Payment Confirmed',
            'message' => 'Your payment for the session booking has been confirmed.',
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
            'action_url' => '/dashboard/payments/'.$this->payment->id,
        ];
    }
}
