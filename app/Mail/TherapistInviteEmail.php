<?php

namespace App\Mail;

use App\Models\TherapistInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TherapistInviteEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public readonly string $signupUrl;

    public function __construct(
        public readonly TherapistInvite $invite,
        public readonly string $inviterName,
    ) {
        $this->signupUrl = rtrim(config('frontend.url'), '/')
            . '/auth/therapist-signup?token=' . $invite->token
            . '&email=' . urlencode($invite->email);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'ve been invited to join ' . config('app.name', 'Onwynd') . ' as a Therapist',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.therapist.invite',
        );
    }
}
