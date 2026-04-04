<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StreakSaverEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public $currentStreak;

    public $hoursLeft;

    public function __construct(User $user, int $currentStreak, int $hoursLeft)
    {
        $this->user = $user;
        $this->currentStreak = $currentStreak;
        $this->hoursLeft = $hoursLeft;
    }

    public function envelope(): Envelope
    {
        $subject = "{$this->user->first_name}, don't lose your {$this->currentStreak}-day streak";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $ctaUrl = config('frontend.url').'/unwind?utm_source=retention&utm_medium=email&utm_campaign=streak-saver';

        return new Content(
            view: 'emails.retention.streak-saver',
            with: [
                'user' => $this->user,
                'currentStreak' => $this->currentStreak,
                'hoursLeft' => $this->hoursLeft,
                'ctaUrl' => $ctaUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
