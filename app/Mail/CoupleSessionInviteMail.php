<?php

namespace App\Mail;

use App\Models\GroupSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CoupleSessionInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $joinUrl;

    public function __construct(
        public GroupSession $session,
        public string $inviteToken,
        public ?string $partnerName,
        public string $inviterName,
        public ?string $partnerRole,   // 'partner_1' or 'partner_2' — for record-keeping only, not shown in email
    ) {
        $this->joinUrl = rtrim(config('frontend.url'), '/').'/group-therapy/join/'.$session->uuid.'?invite_token='.$inviteToken;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->inviterName.' has invited you to a couples therapy session',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.couple-session-invite',
        );
    }
}
