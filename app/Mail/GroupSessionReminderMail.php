<?php

namespace App\Mail;

use App\Models\GroupSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GroupSessionReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $joinUrl;

    public string $timeText;

    public bool $isCouple;

    public function __construct(
        public GroupSession $session,
        public ?string $recipientName,
        public string $label,      // e.g. "24h" or "30min"
        public ?string $inviteToken = null,
    ) {
        $this->isCouple = $session->session_type === 'couple';

        $this->timeText = match (true) {
            str_ends_with($label, 'h')   => rtrim($label, 'h').' hour'.(rtrim($label, 'h') === '1' ? '' : 's'),
            str_ends_with($label, 'min') => rtrim($label, 'min').' minutes',
            default                      => $label,
        };

        // Link back to the session — carry the invite token for guests who haven't accepted yet
        $base = rtrim(config('frontend.url'), '/').'/group-therapy/join/'.$session->uuid;
        $this->joinUrl = $inviteToken ? $base.'?invite_token='.$inviteToken : $base;
    }

    public function envelope(): Envelope
    {
        $subject = $this->isCouple
            ? 'Your couples therapy session starts in '.$this->timeText
            : 'Reminder: '.$this->session->title.' starts in '.$this->timeText;

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.group-session-reminder');
    }
}
