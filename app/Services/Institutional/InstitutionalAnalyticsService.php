<?php

namespace App\Services\Institutional;

use App\Models\AIChat;
use App\Models\CrisisEvent;
use App\Models\Institutional\Organization;
use App\Models\TherapySession;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserAssessmentResult;

class InstitutionalAnalyticsService
{
    public function engagementMetrics(int $organizationId, string $period = '30d'): array
    {
        $org = Organization::findOrFail($organizationId);
        $memberIds = $org->members()->pluck('user_id');

        $from = match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(30),
        };

        $activeUsers = UserActivity::whereIn('user_id', $memberIds)
            ->where('created_at', '>=', $from)
            ->distinct('user_id')
            ->count('user_id');

        $sessionsCompleted = TherapySession::whereIn('patient_id', $memberIds)
            ->where('status', 'completed')
            ->where('ended_at', '>=', $from)
            ->count();

        $aiMessages = AIChat::whereIn('user_id', $memberIds)
            ->where('created_at', '>=', $from)
            ->count();

        $assessmentsCompleted = UserAssessmentResult::whereIn('user_id', $memberIds)
            ->where('created_at', '>=', $from)
            ->count();

        return [
            'organization' => $org->name,
            'period' => $period,
            'active_users' => $activeUsers,
            'sessions_completed' => $sessionsCompleted,
            'ai_messages' => $aiMessages,
            'assessments_completed' => $assessmentsCompleted,
            'member_count' => $memberIds->count(),
        ];
    }

    public function atRiskUsers(int $organizationId): array
    {
        $org = Organization::findOrFail($organizationId);

        // Fetch recent crisis events (last 30 days) for this organization
        $events = CrisisEvent::where('org_id', $organizationId)
            ->where('triggered_at', '>=', now()->subDays(30))
            ->orderByDesc('triggered_at')
            ->get();

        return [
            'count' => $events->count(),
            'events' => $events->map(function ($event) {
                // Anonymize user identifier (e.g., Member #047)
                // Use a stable hash of the user ID + org ID so it's consistent but anonymous
                $hash = substr(hash('sha256', $event->user_id.$event->org_id.'salt'), 0, 3);
                $memberId = "Member #{$hash}";

                return [
                    'id' => $event->id,
                    'uuid' => $event->uuid,
                    'risk_level' => $event->risk_level,
                    'flagged_at' => $event->triggered_at->toIso8601String(),
                    'member_identifier' => $memberId,
                    'status' => $event->status, // pending, reviewed, resolved
                    'reason' => 'AI Crisis Detection', // Hardcoded for now as per requirement
                    'resources_shown' => $event->resources_shown,
                ];
            }),
        ];
    }

    public function monthlyReport(int $organizationId, string $month): array
    {
        $org = Organization::findOrFail($organizationId);
        [$y, $m] = explode('-', $month);
        $start = now()->setYear((int) $y)->setMonth((int) $m)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $memberIds = $org->members()->pluck('user_id');

        return [
            'organization' => $org->name,
            'month' => $month,
            'new_members' => $org->members()->whereBetween('created_at', [$start, $end])->count(),
            'sessions_completed' => TherapySession::whereIn('patient_id', $memberIds)
                ->where('status', 'completed')
                ->whereBetween('ended_at', [$start, $end])
                ->count(),
            'ai_messages' => AIChat::whereIn('user_id', $memberIds)
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'assessments_completed' => UserAssessmentResult::whereIn('user_id', $memberIds)
                ->whereBetween('created_at', [$start, $end])
                ->count(),
        ];
    }
}
