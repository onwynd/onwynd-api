<?php

namespace App\Services;

use App\Models\Okr\OkrAlert;
use App\Models\Okr\OkrKeyResult;
use App\Notifications\OkrHealthChangedNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OkrAlertService
{
    /**
     * Dispatch alerts when a KR's health status transitions.
     * No-op when old == new (stable state, no alert needed).
     *
     * Delivery order: in-app notification → Slack.
     * Each channel is independent — a failure in one does not block others.
     */
    public function dispatch(OkrKeyResult $kr, string $oldHealth, string $newHealth): void
    {
        if ($oldHealth === $newHealth) return;

        $notifiedVia = [];

        // 1 ── In-app + email (existing notification infrastructure)
        $owner = $kr->owner;
        if ($owner) {
            try {
                $owner->notify(new OkrHealthChangedNotification($kr, $oldHealth, $newHealth));
                $notifiedVia[] = 'notification';
                Log::info('OKR: health alert sent via notification', [
                    'kr_id'   => $kr->id,
                    'user_id' => $owner->id,
                ]);
            } catch (\Throwable $e) {
                Log::error('OKR: notification dispatch failed', [
                    'kr_id' => $kr->id,
                    'error' => $e->getMessage(),
                ]);
                // Continue — Slack alert should still fire
            }
        }

        // 2 ── Slack
        $this->sendSlack($kr, $oldHealth, $newHealth, $notifiedVia);

        // 3 ── Persist alert record
        OkrAlert::create([
            'key_result_id'   => $kr->id,
            'alert_type'      => $this->alertType($oldHealth, $newHealth),
            'previous_health' => $oldHealth,
            'new_health'      => $newHealth,
            'notified_via'    => $notifiedVia,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function alertType(string $old, string $new): string
    {
        if ($new === 'on_track')  return 'recovered';
        if ($new === 'at_risk')   return 'drifted_amber';
        return 'drifted_red';
    }

    private function sendSlack(OkrKeyResult $kr, string $oldHealth, string $newHealth, array &$notifiedVia): void
    {
        $token   = config('services.slack.notifications.bot_user_oauth_token');
        $channel = config('services.slack.notifications.channel');

        if (empty($token) || empty($channel)) {
            Log::debug('OKR: Slack not configured — skipping', ['kr_id' => $kr->id]);
            return;
        }

        $emoji = match ($newHealth) {
            'on_track'  => ':large_green_circle:',
            'at_risk'   => ':large_yellow_circle:',
            'off_track' => ':red_circle:',
            default     => ':white_circle:',
        };

        $statusLabel = str_replace('_', ' ', strtoupper($newHealth));
        $progress    = round($kr->progress, 1);
        $objective   = $kr->objective?->title ?? 'Unknown Objective';
        $owner       = $kr->owner?->first_name . ' ' . $kr->owner?->last_name;

        $text = "{$emoji} *OKR Health Alert — {$statusLabel}*\n"
              . ">*Key Result:* {$kr->title}\n"
              . ">*Objective:* {$objective}\n"
              . ">*Progress:* {$progress}% | *Owner:* {$owner}\n"
              . ">Status changed: `" . str_replace('_', ' ', $oldHealth) . "` → `" . str_replace('_', ' ', $newHealth) . "`";

        try {
            $response = Http::withToken($token)
                ->timeout(5)
                ->post('https://slack.com/api/chat.postMessage', [
                    'channel' => $channel,
                    'text'    => $text,
                    'mrkdwn'  => true,
                ]);

            if ($response->successful() && $response->json('ok')) {
                $notifiedVia[] = 'slack';
                Log::info('OKR: Slack alert sent', ['kr_id' => $kr->id]);
            } else {
                Log::warning('OKR: Slack returned non-ok response', [
                    'kr_id'       => $kr->id,
                    'slack_error' => $response->json('error'),
                ]);
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('OKR: Slack connection failed', ['kr_id' => $kr->id, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('OKR: Slack unexpected error', ['kr_id' => $kr->id, 'error' => $e->getMessage()]);
        }
    }
}
