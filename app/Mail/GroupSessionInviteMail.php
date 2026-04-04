<?php

namespace App\Mail;

use App\Models\GroupSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class GroupSessionInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public $session;

    public $inviteToken;

    public $joinUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(GroupSession $session, string $inviteToken)
    {
        $this->session = $session;
        $this->inviteToken = $inviteToken;
        $this->joinUrl = rtrim(config('frontend.url'), '/').'/group-therapy/join/'.$session->uuid.'?invite_token='.$inviteToken;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitation: '.Str::headline($this->session->session_type).' Therapy Session',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.group-session-invite',
        );
    }
}
