<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TherapistVerificationStatus extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $therapistName;

    public $status;

    public $reason;

    public $link;

    /**
     * Create a new message instance.
     */
    public function __construct($therapistName, $status, $reason = null, $link = null)
    {
        $this->therapistName = $therapistName;
        $this->status = $status;
        $this->reason = $reason;
        $this->link = $link ?? url('/therapist/dashboard');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->status === 'approved'
            ? 'Your Therapist Profile is Approved!'
            : 'Update Required for Your Therapist Profile';

        return new Envelope(
            subject: $subject.' - '.config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.therapist.verification-status',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
