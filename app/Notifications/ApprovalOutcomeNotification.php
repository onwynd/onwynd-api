<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use Illuminate\Notifications\Messages\MailMessage;

class ApprovalOutcomeNotification extends BaseNotification
{
    protected string $preferenceKey = 'push_notifications';

    public function __construct(
        private readonly ApprovalRequest $request,
        private readonly string $outcome,   // approved | rejected | under_review
        private readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->notificationSetting?->push_notifications) $channels[] = 'fcm';
        if ($notifiable->notificationSetting?->email_notifications) $channels[] = 'mail';
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$emoji, $subject] = match ($this->outcome) {
            'approved'     => ['✅', "Approved: {$this->request->title}"],
            'rejected'     => ['❌', "Rejected: {$this->request->title}"],
            'under_review' => ['💬', "More Info Needed: {$this->request->title}"],
            default        => ['🔔', "Update: {$this->request->title}"],
        };

        $mail = (new MailMessage)
            ->subject("{$emoji} {$subject}")
            ->greeting("Hello {$notifiable->first_name}!")
            ->line("Your request has been **" . ucfirst(str_replace('_', ' ', $this->outcome)) . "**.");

        if ($this->reason) {
            $label = $this->outcome === 'under_review' ? 'Question / Comment' : 'Reason';
            $mail->line("**{$label}:** {$this->reason}");
        }

        return $mail->action('View Request', url("/approvals/{$this->request->uuid}"));
    }

    public function toFcm(object $notifiable): array
    {
        $emoji = match ($this->outcome) {
            'approved'     => '✅',
            'rejected'     => '❌',
            'under_review' => '💬',
            default        => '🔔',
        };

        return [
            'title' => "{$emoji} Request " . ucfirst($this->outcome),
            'body'  => $this->request->title . ($this->reason ? ": {$this->reason}" : ''),
            'data'  => [
                'type'         => 'approval_outcome',
                'outcome'      => $this->outcome,
                'request_uuid' => $this->request->uuid,
                'action_url'   => "/approvals/{$this->request->uuid}",
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'approval_outcome',
            'title'        => "Request " . ucfirst(str_replace('_', ' ', $this->outcome)) . ": {$this->request->title}",
            'message'      => $this->reason ?? "Your request has been " . str_replace('_', ' ', $this->outcome) . ".",
            'outcome'      => $this->outcome,
            'request_uuid' => $this->request->uuid,
            'action_url'   => "/approvals/{$this->request->uuid}",
        ];
    }
}
