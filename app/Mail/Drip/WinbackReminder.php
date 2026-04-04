<?php

namespace App\Mail\Drip;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WinbackReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "We haven't seen you in a while, {$this->user->first_name} — your wellness matters");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.drip.winback-reminder', with: [
            'user' => $this->user,
            'ctaUrl' => config('frontend.url').'/dashboard/chat?utm_source=drip&utm_medium=email&utm_campaign=winback',
        ]);
    }
}
