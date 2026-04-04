<?php

namespace App\Mail;

use App\Models\Partner;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InstitutionalBiweeklyReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public readonly ?string $logoUrl;

    public function __construct(
        public readonly Partner $partner,
        public readonly array   $stats,
        public readonly Carbon  $periodStart,
        public readonly Carbon  $periodEnd,
    ) {
        // Resolve logo: partner logo → Onwynd branding logo from DB → Onwynd default logo asset
        $this->logoUrl = $partner->logo
            ?? Setting::getValue('logo_url')
            ?? (config('app.url') . '/img/logo.png');
    }

    public function envelope(): Envelope
    {
        $from = $this->periodStart->format('M j');
        $to   = $this->periodEnd->format('M j, Y');

        return new Envelope(
            subject: "Biweekly Report: {$this->partner->name} — {$from} to {$to}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reports.institutional-biweekly',
        );
    }
}
