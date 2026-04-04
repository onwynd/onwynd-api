<?php

namespace App\Mail;

use App\Models\WaitlistSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistInviteEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $signupUrl;

    public function __construct(public WaitlistSubmission $submission)
    {
        $this->signupUrl = config('frontend.url').'/auth/signup?invite='
            .urlencode($submission->email);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Onwynd invite is here 🎉',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.waitlist.invite',
            with: [
                'firstName'   => $this->submission->first_name,
                'signupUrl'   => $this->signupUrl,
                'previewText' => 'Your Invitation',
            ],
        );
    }
}
