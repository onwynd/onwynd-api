<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInactivityRetentionEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public $daysInactive;

    public $subjectLine;

    public $previewText;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, int $daysInactive)
    {
        $this->user = $user;
        $this->daysInactive = $daysInactive;

        // Personalize based on inactivity duration
        $this->subjectLine = $this->getSubjectLine();
        $this->previewText = $this->getPreviewText();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.retention.user-inactivity',
            with: [
                'user' => $this->user,
                'daysInactive' => $this->daysInactive,
                'previewText' => $this->previewText,
                'ctaUrl' => $this->getCtaUrl(),
                'ctaText' => $this->getCtaText(),
                'suggestedActivities' => $this->getSuggestedActivities(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    private function getSubjectLine(): string
    {
        $subjects = [
            3 => "{$this->user->first_name}, your wellness journey misses you 🌱",
            5 => "{$this->user->first_name}, don't lose your progress 💪",
            7 => "{$this->user->first_name}, we miss you! Come back to Onwynd 🌟",
            14 => "{$this->user->first_name}, your mental health matters - let's restart 🌈",
        ];

        // Find the closest match for days inactive
        $closest = 3;
        foreach ([14, 7, 5] as $days) {
            if ($this->daysInactive >= $days) {
                $closest = $days;
                break;
            }
        }

        return $subjects[$closest] ?? "{$this->user->first_name}, ready to continue your journey?";
    }

    private function getPreviewText(): string
    {
        $texts = [
            3 => "It's been {$this->daysInactive} days since your last wellness activity. Let's get back on track!",
            5 => "Your {$this->daysInactive}-day break might be affecting your momentum. Small steps make big differences.",
            7 => "A week away from your wellness routine. The hardest part is starting again - we're here to help.",
            14 => "Two weeks is a long time. Your mental health journey is important, and we're ready when you are.",
        ];

        $closest = 3;
        foreach ([14, 7, 5] as $days) {
            if ($this->daysInactive >= $days) {
                $closest = $days;
                break;
            }
        }

        return $texts[$closest] ?? "It's been {$this->daysInactive} days. Ready to continue your wellness journey?";
    }

    private function getCtaUrl(): string
    {
        return config('frontend.url').'/unwind?utm_source=retention&utm_medium=email&utm_campaign=inactivity';
    }

    private function getCtaText(): string
    {
        $texts = [
            3 => 'Continue Where You Left Off',
            5 => 'Get Back on Track',
            7 => 'Restart Your Journey',
            14 => 'Start Fresh Today',
        ];

        $closest = 3;
        foreach ([14, 7, 5] as $days) {
            if ($this->daysInactive >= $days) {
                $closest = $days;
                break;
            }
        }

        return $texts[$closest] ?? 'Return to Onwynd';
    }

    private function getSuggestedActivities(): array
    {
        return [
            '5-minute breathing exercise' => 'Perfect for getting back into the routine',
            'Quick mood check-in' => 'See how you\'re feeling today',
            'Gratitude journaling' => 'Start with something simple and positive',
            'Sleep soundscape' => 'End your day with calming audio',
        ];
    }
}
