<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StreakBreakNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly int $daysSinceActivity,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "We miss you, {$this->user->first_name} — let's rebuild together",
        );
    }

    public function content(): Content
    {
        $ctaUrl = config('frontend.url').'/unwind?utm_source=retention&utm_medium=email&utm_campaign=streak-break';

        return new Content(
            view: 'emails.retention.streak-break',
            with: [
                'user' => $this->user,
                'daysSinceActivity' => $this->daysSinceActivity,
                'ctaUrl' => $ctaUrl,
            ],
        );
    }
}
