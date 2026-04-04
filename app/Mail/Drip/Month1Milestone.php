<?php

namespace App\Mail\Drip;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Month1Milestone extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "One month of caring for yourself — we're proud of you, {$this->user->first_name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.drip.month1-milestone', with: [
            'user' => $this->user,
            'ctaUrl' => config('frontend.url').'/unwind?utm_source=drip&utm_medium=email&utm_campaign=month1',
        ]);
    }
}
