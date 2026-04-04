<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use Illuminate\Notifications\Messages\MailMessage;

class ApprovalRequestedNotification extends BaseNotification
{
    protected string $preferenceKey = 'push_notifications';

    public function __construct(
        private readonly ApprovalRequest $request,
        private readonly ApprovalStep $step,
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
        return (new MailMessage)
            ->subject("🔔 Action Required: {$this->request->title}")
            ->greeting("Hello {$notifiable->first_name}!")
            ->line("You have an approval request waiting for your action.")
            ->line("**{$this->request->title}**")
            ->line("Step: {$this->step->step_label}")
            ->line("Requested by: {$this->request->requester?->first_name} {$this->request->requester?->last_name}")
            ->action('Review Request', url("/approvals/{$this->request->uuid}"));
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => "🔔 Approval Needed",
            'body'  => "{$this->request->title} — {$this->step->step_label}",
            'data'  => [
                'type'         => 'approval_requested',
                'request_uuid' => $this->request->uuid,
                'request_type' => $this->request->type,
                'action_url'   => "/approvals/{$this->request->uuid}",
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'approval_requested',
            'title'        => "Approval Required: {$this->request->title}",
            'message'      => "Step {$this->request->current_step} of {$this->request->total_steps}: {$this->step->step_label}",
            'request_uuid' => $this->request->uuid,
            'request_type' => $this->request->type,
            'action_url'   => "/approvals/{$this->request->uuid}",
        ];
    }
}
