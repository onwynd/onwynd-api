<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $resetUrl = rtrim(config('frontend.url', 'https://onwynd.com'), '/')
            .'/auth/reset-password'
            .'?token='.urlencode($this->token)
            .'&email='.urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Reset your Onwynd password')
            ->line('We received a request to reset the password for your Onwynd account.')
            ->action('Reset Password', $resetUrl)
            ->line('This link expires in 60 minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }

    public function toArray($notifiable): array
    {
        return [];
    }
}
