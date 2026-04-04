<?php

namespace App\Mail\Drip;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Week1Summary extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your first week on Onwynd — here's what you've built");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.drip.week1-summary', with: [
            'user' => $this->user,
            'ctaUrl' => config('frontend.url').'/dashboard?utm_source=drip&utm_medium=email&utm_campaign=week1',
        ]);
    }
}
