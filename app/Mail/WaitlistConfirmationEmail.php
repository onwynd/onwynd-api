<?php

namespace App\Mail;

use App\Models\WaitlistSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistConfirmationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public WaitlistSubmission $submission) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'therapist'   => "You're on the Onwynd therapist waitlist 🧠",
            'institution' => "Onwynd for your organisation — you're on the list 🏛️",
        ];

        return new Envelope(
            subject: $subjects[$this->submission->role] ?? "You're on the Onwynd waitlist 🌿",
        );
    }

    public function content(): Content
    {
        $templates = [
            'therapist'   => 'emails.waitlist.confirmation-therapist',
            'institution' => 'emails.waitlist.confirmation-institution',
        ];

        $view = $templates[$this->submission->role] ?? 'emails.waitlist.confirmation';

        return new Content(
            view: $view,
            with: [
                'firstName'       => $this->submission->first_name,
                'role'            => $this->submission->role,
                'institutionType' => $this->submission->institution_type,
                'organizationName'=> $this->submission->organization_name,
                'previewText'     => 'Waitlist Confirmation',
            ],
        );
    }
}
