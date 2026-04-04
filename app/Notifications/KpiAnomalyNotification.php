<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * KpiAnomalyNotification
 * ──────────────────────
 * Sent when a KPI crosses an anomaly threshold (warn or alert).
 * Delivered via database, FCM push, and optionally email.
 *
 * Triggered by: KPI Snapshot Controller or a scheduled command
 * that monitors KPI values against the thresholds in lib/kpi/config.ts.
 */
class KpiAnomalyNotification extends BaseNotification
{
    protected string $preferenceKey = 'push_notifications';

    public function __construct(
        private readonly string $kpiLabel,
        private readonly string $kpiKey,
        private readonly float|int|string $currentValue,
        private readonly string $severity, // 'warn' | 'alert'
        private readonly string $role,
        private readonly ?string $actionUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (method_exists($notifiable, 'notificationSetting')
            && $notifiable->notificationSetting?->push_notifications) {
            $channels[] = 'fcm';
        }
        // Only email on alert-level anomalies to avoid inbox noise
        if ($this->severity === 'alert'
            && method_exists($notifiable, 'notificationSetting')
            && $notifiable->notificationSetting?->email_notifications) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $emoji   = $this->severity === 'alert' ? '🔴' : '🟡';
        $subject = "{$emoji} KPI Alert: {$this->kpiLabel} needs attention";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->first_name}!")
            ->line("{$emoji} **{$this->kpiLabel}** has crossed an anomaly threshold.")
            ->line("Current value: **{$this->currentValue}**")
            ->line("Severity: " . ucfirst($this->severity))
            ->when($this->actionUrl, fn ($mail) => $mail->action('View Dashboard', url($this->actionUrl)));
    }

    /**
     * Get the push representation of the notification (FCM).
     */
    public function toFcm(object $notifiable): array
    {
        $emoji = $this->severity === 'alert' ? '🔴' : '🟡';

        return [
            'title' => "{$emoji} KPI Anomaly: {$this->kpiLabel}",
            'body'  => "Current value: {$this->currentValue} — requires attention",
            'data'  => [
                'type'       => 'kpi_anomaly',
                'kpi_key'    => $this->kpiKey,
                'severity'   => $this->severity,
                'role'       => $this->role,
                'action_url' => $this->actionUrl ?? '/dashboard',
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'kpi_anomaly',
            'title'         => "KPI Alert: {$this->kpiLabel}",
            'message'       => "Current value ({$this->currentValue}) has crossed a {$this->severity} threshold.",
            'kpi_key'       => $this->kpiKey,
            'severity'      => $this->severity,
            'current_value' => $this->currentValue,
            'role'          => $this->role,
            'action_url'    => $this->actionUrl ?? '/dashboard',
        ];
    }
}
