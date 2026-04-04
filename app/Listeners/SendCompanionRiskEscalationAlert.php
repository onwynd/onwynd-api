<?php

namespace App\Listeners;

use App\Events\AI\CompanionRiskEscalationEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCompanionRiskEscalationAlert
{
    public function handle(CompanionRiskEscalationEvent $event): void
    {
        try {
            $user = User::find($event->user_id);
            $risk = $event->risk ?? [];
            $subject = 'Companion Risk Escalation Alert';

            // Determine recipients: all clinical advisors (+ clinical managers/CEO)
            $recipients = User::whereHas('role', function ($q) {
                $q->whereIn('slug', ['clinical_advisor', 'clinical_manager', 'ceo']);
            })->pluck('email')->filter()->unique()->values()->all();

            if (empty($recipients)) {
                $fallback = config('mail.clinical_alert_address') ?: 'help@onwynd.com';
                $recipients = [$fallback];
            }

            $lines = [
                'A high-risk message was detected in AI Companion.',
                '',
                'User:',
                ' - ID: '.($user->id ?? 'N/A'),
                ' - Name: '.(($user->name ?? $user->full_name ?? null) ?: 'N/A'),
                ' - Email: '.($user->email ?? 'N/A'),
                '',
                'Message:',
                $event->message ?? '',
                '',
                'Risk Analysis:',
                ' - Level: '.($risk['risk_level'] ?? $risk['level'] ?? 'unknown'),
                ' - Score: '.($risk['score'] ?? 'n/a'),
                ' - Flags: '.(is_array($risk['flags'] ?? null) ? implode(', ', $risk['flags']) : ($risk['flags'] ?? 'n/a')),
                '',
                'Timestamp: '.now()->toDateTimeString(),
            ];

            $body = implode("\n", $lines);

            Mail::raw($body, function ($message) use ($subject, $recipients) {
                $message->to(array_shift($recipients));
                if (! empty($recipients)) {
                    $message->cc($recipients);
                }
                $message->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to send CompanionRiskEscalation alert email', [
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('CompanionRiskEscalationEvent handled by SendCompanionRiskEscalationAlert', [
            'user_id' => $event->user_id,
        ]);
    }
}
