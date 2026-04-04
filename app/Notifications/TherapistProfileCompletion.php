<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TherapistProfileCompletion extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $firstName) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $dashboardUrl = url('/therapist/dashboard');

        return (new MailMessage)
            ->subject('Complete Your Therapist Profile — '.config('app.name'))
            ->greeting("Hi {$this->firstName}!")
            ->line("Welcome to ".config('app.name')."'s therapist network.")
            ->line('Your account has been granted therapist access. Please take a few minutes to complete your profile so patients can find and book sessions with you.')
            ->action('Complete My Profile', $dashboardUrl)
            ->line('Once your profile is submitted, our team will review it within 1–2 business days.')
            ->line('If you have any questions, reach out to '.config('onwynd.support_email', 'hello@onwynd.com').'.');
    }
}
