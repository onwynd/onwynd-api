<?php

namespace App\Mail;

use App\Models\WaitlistSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistLeadAlertEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $priority;  // 'high' | 'medium'

    public function __construct(
        public WaitlistSubmission $submission,
        public string $recipientName,
    ) {
        $this->priority = $submission->role === 'institution' ? 'high' : 'medium';
    }

    public function envelope(): Envelope
    {
        $prefix = $this->priority === 'high' ? '🔴 High-priority lead' : '🟡 New therapist lead';

        return new Envelope(
            subject: "{$prefix}: {$this->submission->first_name} {$this->submission->last_name} ({$this->submission->role})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.waitlist-lead-alert',
            with: [
                'submission'     => $this->submission,
                'recipientName'  => $this->recipientName,
                'priority'       => $this->priority,
                'previewText'    => 'New waitlist lead requires your attention',
            ],
        );
    }
}
