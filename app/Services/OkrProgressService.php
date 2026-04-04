<?php

namespace App\Services;

use App\Models\Dashboard\DashboardMetric;
use App\Models\Okr\OkrCheckIn;
use App\Models\Okr\OkrKeyResult;
use Illuminate\Support\Facades\Log;

class OkrProgressService
{
    // Progress must be within this many % of pace to be ON_TRACK.
    // Below AT_RISK_GAP → at_risk. Below OFF_TRACK_GAP → off_track.
    const AT_RISK_GAP   = 10;
    const OFF_TRACK_GAP = 25;

    // ── Core calculations ─────────────────────────────────────────────────────

    /**
     * Progress (%): how far along we are toward the target.
     *   (current - start) / (target - start) × 100
     * Returns 0 and logs a warning when start == target (division-by-zero guard).
     * Allows values above 100 for exceeded targets.
     */
    public function calculateProgress(OkrKeyResult $kr): float
    {
        $range = $kr->target_value - $kr->start_value;
        if (abs($range) < 0.0001) {
            Log::warning('OKR: KR has equal start/target — progress set to 0', ['kr_id' => $kr->id]);
            return 0.0;
        }
        return round((($kr->current_value - $kr->start_value) / $range) * 100, 2);
    }

    /**
     * Pace (%): what % progress we should be at by today to finish on time.
     *   days_elapsed / total_days × 100
     * Clamped 0–100 so it never exceeds the deadline.
     */
    public function calculatePace(OkrKeyResult $kr): float
    {
        $start      = $kr->created_at->startOfDay();
        $end        = $kr->due_date->endOfDay();
        $totalDays  = max(1, $start->diffInDays($end));
        $elapsedDays = (int) min($start->diffInDays(now()), $totalDays);

        return round(($elapsedDays / $totalDays) * 100, 2);
    }

    /**
     * Health: compares progress against pace using gap thresholds.
     *
     * gap = pace - progress
     *   gap < AT_RISK_GAP   → on_track
     *   gap < OFF_TRACK_GAP → at_risk
     *   gap >= OFF_TRACK_GAP → off_track
     */
    public function getHealth(OkrKeyResult $kr): string
    {
        $gap = $this->calculatePace($kr) - $this->calculateProgress($kr);

        if ($gap >= self::OFF_TRACK_GAP) return 'off_track';
        if ($gap >= self::AT_RISK_GAP)   return 'at_risk';
        return 'on_track';
    }

    // ── Auto-refresh ──────────────────────────────────────────────────────────

    /**
     * Fetch the latest value for an auto-bound KR from DashboardMetric.
     * Returns null if metric_key is unset, no matching record exists,
     * or the stored value is non-numeric.
     */
    public function fetchAutoValue(OkrKeyResult $kr): ?float
    {
        if (empty($kr->metric_key)) return null;

        $metric = DashboardMetric::where('metric_key', $kr->metric_key)
            ->latest('updated_at')
            ->first();

        if (! $metric) {
            Log::warning('OKR: no DashboardMetric row for metric_key', [
                'kr_id'      => $kr->id,
                'metric_key' => $kr->metric_key,
            ]);
            return null;
        }

        // metric_value is cast as json — unwrap common scalar shapes
        $raw = $metric->metric_value;
        if (is_array($raw)) {
            $raw = $raw['value'] ?? $raw['count'] ?? $raw['total'] ?? $raw['amount'] ?? null;
        }

        return is_numeric($raw) ? (float) $raw : null;
    }

    /**
     * Refresh a single auto-bound KR.
     *
     * Returns [old_health, new_health] when a refresh happened (even if health unchanged).
     * Returns null when skipped (manual KR / no metric / already refreshed today).
     */
    public function refresh(OkrKeyResult $kr): ?array
    {
        if ($kr->metric_type !== 'auto') return null;

        $newValue = $this->fetchAutoValue($kr);
        if ($newValue === null) return null;

        // Idempotency: one automated check-in per KR per calendar day
        $alreadyToday = OkrCheckIn::where('key_result_id', $kr->id)
            ->where('is_automated', true)
            ->whereDate('recorded_at', today())
            ->exists();

        if ($alreadyToday) return null;

        $oldHealth = $kr->health_status;

        $kr->current_value      = $newValue;
        $kr->health_status      = $this->getHealth($kr); // uses updated current_value
        $kr->last_refreshed_at  = now();
        $kr->save();

        OkrCheckIn::create([
            'key_result_id' => $kr->id,
            'value'         => $newValue,
            'is_automated'  => true,
            'recorded_by'   => null,
            'recorded_at'   => now(),
        ]);

        Log::info('OKR: KR auto-refreshed', [
            'kr_id'      => $kr->id,
            'old_health' => $oldHealth,
            'new_health' => $kr->health_status,
            'value'      => $newValue,
        ]);

        return [$oldHealth, $kr->health_status];
    }

    /**
     * Recalculate health for a manual KR after a check-in.
     * Saves the updated health_status if it changed.
     * Returns [old_health, new_health].
     */
    public function recalculateManual(OkrKeyResult $kr): array
    {
        $oldHealth = $kr->health_status;
        $newHealth = $this->getHealth($kr);

        if ($oldHealth !== $newHealth) {
            $kr->health_status = $newHealth;
            $kr->save();
        }

        return [$oldHealth, $newHealth];
    }

    // ── Company-level ─────────────────────────────────────────────────────────

    /**
     * Company health score (0–100).
     * Weighted: on_track = 100 pts, at_risk = 50 pts, off_track = 0 pts.
     * Average across all active KRs.
     */
    public function companyHealthScore(): float
    {
        $krs = OkrKeyResult::whereHas('objective', fn ($q) => $q->where('status', 'active'))->get();
        if ($krs->isEmpty()) return 0.0;

        return round($krs->avg(fn ($kr) => match ($kr->health_status) {
            'on_track'  => 100,
            'at_risk'   => 50,
            'off_track' => 0,
            default     => 50,
        }), 1);
    }

    // ── Bindable metrics catalogue ────────────────────────────────────────────

    /**
     * Metrics known to exist in DashboardMetric that can be auto-bound to a KR.
     * Grouped by department for the frontend picker.
     */
    public function bindableMetrics(): array
    {
        return [
            'growth' => [
                'total_users'            => 'Total Users',
                'new_users_today'        => 'New Users (Today)',
                'new_users_this_week'    => 'New Users (This Week)',
                'new_users_this_month'   => 'New Users (This Month)',
                'dau'                    => 'Daily Active Users (DAU)',
                'mau'                    => 'Monthly Active Users (MAU)',
            ],
            'revenue' => [
                'total_revenue'          => 'Total Revenue',
                'mrr'                    => 'Monthly Recurring Revenue (MRR)',
                'arr'                    => 'Annual Recurring Revenue (ARR)',
                'revenue_this_month'     => 'Revenue (This Month)',
                'average_session_value'  => 'Average Session Value',
            ],
            'sessions' => [
                'total_sessions'         => 'Total Sessions',
                'sessions_this_month'    => 'Sessions (This Month)',
                'session_completion_rate'=> 'Session Completion Rate (%)',
                'avg_session_duration'   => 'Avg Session Duration (min)',
            ],
            'therapists' => [
                'total_therapists'       => 'Total Therapists',
                'active_therapists'      => 'Active Therapists',
                'therapist_utilization'  => 'Therapist Utilization Rate (%)',
            ],
            'patients' => [
                'total_patients'         => 'Total Patients',
                'active_patients'        => 'Active Patients (30d)',
                'retention_rate'         => 'Patient Retention Rate (%)',
                'churn_rate'             => 'Churn Rate (%)',
            ],
            'engagement' => [
                'mood_log_rate'          => 'Mood Logging Rate (%)',
                'assessment_completion'  => 'Assessment Completion Rate (%)',
            ],
            'sales' => [
                'deals_closed_won'       => 'Deals Closed (Won)',
                'deals_pipeline_value'   => 'Pipeline Value ($)',
                'leads_this_month'       => 'Leads (This Month)',
            ],
            'support' => [
                'open_tickets'           => 'Open Support Tickets',
                'avg_response_time'      => 'Avg Support Response Time (hrs)',
                'satisfaction_score'     => 'Customer Satisfaction Score',
            ],
        ];
    }
}
