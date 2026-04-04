<?php

namespace App\Mail\Drip;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FirstSessionBadge extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You did it, {$this->user->first_name} — your first therapy session badge is waiting");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.drip.first-session-badge', with: [
            'user' => $this->user,
            'ctaUrl' => config('frontend.url').'/achievements?utm_source=drip&utm_medium=email&utm_campaign=first-session',
        ]);
    }
}
