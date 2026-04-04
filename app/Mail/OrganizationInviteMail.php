<?php

namespace App\Mail;

use App\Models\Institutional\Organization;
use App\Models\Institutional\OrganizationInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $acceptUrl;

    public function __construct(
        public OrganizationInvite $invite,
        public Organization $organization,
    ) {
        $this->acceptUrl = rtrim(config('frontend.url'), '/').'/invite/'.$invite->token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'ve been invited to join '.$this->organization->name.' on Onwynd',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.institutional.organization-invite',
        );
    }
}
