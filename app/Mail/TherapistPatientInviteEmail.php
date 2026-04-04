<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\TherapistPatientInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TherapistPatientInviteEmail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $signupUrl;
    public readonly string $logoUrl;
    public readonly string $therapistName;

    public function __construct(
        public readonly TherapistPatientInvite $invite,
    ) {
        $therapist      = $invite->therapist;
        $this->therapistName = trim($therapist->first_name . ' ' . $therapist->last_name);

        $this->signupUrl = rtrim(config('frontend.url'), '/')
            . '/auth/patient-signup'
            . '?therapist_token=' . $invite->token
            . '&email=' . urlencode($invite->email);

        // Onwynd branding logo (no partner override — this comes from the platform)
        $this->logoUrl = Setting::getValue('logo_url')
            ?? (config('app.url') . '/img/logo.png');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your therapist ' . $this->therapistName . ' invited you to Onwynd',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.therapist.patient-invite',
        );
    }
}
