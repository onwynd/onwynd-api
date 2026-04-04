<?php

namespace App\Mail\Drip;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Day3CheckIn extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->user->first_name}, how are you settling in?");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.drip.day3-checkin', with: [
            'user' => $this->user,
            'ctaUrl' => config('frontend.url').'/dashboard/mood?utm_source=drip&utm_medium=email&utm_campaign=day3',
        ]);
    }
}
