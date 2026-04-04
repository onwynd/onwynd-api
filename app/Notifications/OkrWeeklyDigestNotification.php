<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OkrWeeklyDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly array $digestData,
        private readonly string $quarter,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $score = $this->digestData['health_score'] ?? 0;
        $emoji = match (true) {
            $score >= 80 => '🟢',
            $score >= 50 => '🟡',
            default      => '🔴',
        };

        return (new MailMessage)
            ->subject("{$emoji} Weekly OKR Digest — {$this->quarter}")
            ->view('emails.okr.weekly-digest', [
                'data'            => $this->digestData,
                'quarter'         => $this->quarter,
                'notifiable'      => $notifiable,
                'isTransactional' => true,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'okr_weekly_digest',
            'title'        => "Weekly OKR Digest — {$this->quarter}",
            'message'      => 'Your weekly OKR digest is ready. Health score: ' . ($this->digestData['health_score'] ?? 0) . '/100.',
            'health_score' => $this->digestData['health_score'] ?? 0,
            'quarter'      => $this->quarter,
            'action_url'   => '/okr',
        ];
    }
}
