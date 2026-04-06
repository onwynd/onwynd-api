<?php

namespace App\Http\Controllers\API\V1\Kpi;

use App\Http\Controllers\API\BaseController;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use App\Models\Session;
use App\Models\Therapist;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\SupportTicket;
use App\Models\Campaign;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * KPI Snapshot Controller
 * ───────────────────────
 * Returns a batched KPI snapshot for a given role.
 * All KPIs are returned in a single response to avoid N+1 API calls.
 *
 * GET /api/v1/kpi/snapshot?role={slug}
 *
 * Cache: 1 hour per role slug.
 */
class SnapshotController extends BaseController
{
    private const CACHE_TTL = 3600; // 1 hour

    public function __invoke(Request $request)
    {
        $role = $request->query('role', auth()->user()?->role ?? 'employee');
        $cacheKey = "kpi_snapshot_{$role}";

        $snapshot = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($role) {
            return $this->buildSnapshot($role);
        });

        return $this->sendResponse($snapshot, 'KPI snapshot retrieved');
    }

    private function buildSnapshot(string $role): array
    {
        try {
            return match (true) {
                in_array($role, ['admin', 'super_admin', 'founder'])  => $this->adminSnapshot(),
                $role === 'president'                                   => $this->presidentSnapshot(),
                $role === 'ceo'                                         => $this->ceoSnapshot(),
                $role === 'coo'                                         => $this->cooSnapshot(),
                $role === 'cgo'                                         => $this->cgoSnapshot(),
                $role === 'cfo'                                         => $this->cfoSnapshot(),
                $role === 'audit'                                       => $this->auditSnapshot(),
                $role === 'vp_sales'                                    => $this->vpSalesSnapshot(),
                $role === 'vp_marketing'                                => $this->vpMarketingSnapshot(),
                $role === 'vp_operations'                               => $this->vpOpsSnapshot(),
                $role === 'vp_product'                                  => $this->vpProductSnapshot(),
                $role === 'finance'                                     => $this->financeSnapshot(),
                $role === 'marketing'                                   => $this->marketingSnapshot(),
                $role === 'sales'                                       => $this->salesSnapshot(),
                $role === 'support'                                     => $this->supportSnapshot(),
                $role === 'hr'                                          => $this->hrSnapshot(),
                default                                                 => [],
            };
        } catch (\Throwable $e) {
            Log::error("KPI snapshot failed for role={$role}", ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ── Shared metrics ──────────────────────────────────────────────────────

    private function totalRevenue(): float
    {
        return (float) Payment::where('status', 'successful')->sum('amount');
    }

    private function mrr(): float
    {
        return (float) Payment::where('status', 'successful')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');
    }

    private function activeUsers(): int
    {
        return User::where('last_login_at', '>=', now()->subDays(30))->count();
    }

    private function sessionsThisMonth(): int
    {
        return Session::where('created_at', '>=', now()->startOfMonth())->count();
    }

    private function sessionsLive(): int
    {
        return Session::where('status', 'in_progress')->count();
    }

    private function openTickets(): int
    {
        return class_exists(SupportTicket::class)
            ? SupportTicket::whereIn('status', ['open', 'pending'])->count()
            : 0;
    }

    private function newLeadsMtd(): int
    {
        return class_exists(Lead::class)
            ? Lead::where('created_at', '>=', now()->startOfMonth())->count()
            : 0;
    }

    private function activeCampaigns(): int
    {
        return class_exists(Campaign::class)
            ? Campaign::where('status', 'active')->count()
            : 0;
    }

    // ── Role snapshots ───────────────────────────────────────────────────────

    private function adminSnapshot(): array
    {
        return [
            'total_revenue'        => $this->totalRevenue(),
            'active_users'         => $this->activeUsers(),
            'sessions_this_month'  => $this->sessionsThisMonth(),
            'therapist_count'      => Therapist::where('status', 'approved')->count(),
            'open_support_tickets' => $this->openTickets(),
            'churn_rate'           => 0, // Calculated by analytics service
        ];
    }

    private function presidentSnapshot(): array
    {
        return [
            'total_revenue'        => $this->totalRevenue(),
            'active_users'         => $this->activeUsers(),
            'sessions_this_month'  => $this->sessionsThisMonth(),
            'company_okr_health'   => 0, // Filled by OkrProgressService
            'open_alerts'          => 0,
            'employee_count'       => User::whereIn('role', ['admin', 'hr', 'finance', 'sales', 'marketing', 'support', 'tech', 'tech_team', 'employee', 'manager', 'ceo', 'coo', 'cgo', 'cfo', 'secretary'])->count(),
        ];
    }

    private function ceoSnapshot(): array
    {
        $prevMonthRevenue = (float) Payment::where('status', 'successful')
            ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->sum('amount');
        $mrr = $this->mrr();
        $growthPct = $prevMonthRevenue > 0 ? (($mrr - $prevMonthRevenue) / $prevMonthRevenue) * 100 : 0;

        return [
            'mrr'                  => $mrr,
            'active_users'         => $this->activeUsers(),
            'revenue_growth_pct'   => round($growthPct, 1),
            'cac'                  => 0,
            'churn_rate'           => 0,
            'okr_health_score'     => 0,
        ];
    }

    private function cooSnapshot(): array
    {
        return [
            'sessions_live'        => $this->sessionsLive(),
            'open_support_tickets' => $this->openTickets(),
            'win_rate'             => 0,
            'avg_resolution_h'     => 0,
            'on_leave_today'       => 0,
            'api_error_rate'       => 0,
        ];
    }

    private function cgoSnapshot(): array
    {
        return [
            'new_leads_mtd'        => $this->newLeadsMtd(),
            'active_campaigns'     => $this->activeCampaigns(),
            'subscriber_count'     => class_exists(Subscriber::class) ? Subscriber::count() : 0,
            'email_open_rate'      => 0,
            'ambassador_count'     => User::where('role', 'ambassador')->count(),
            'cac'                  => 0,
        ];
    }

    private function cfoSnapshot(): array
    {
        $revenue  = $this->totalRevenue();
        $expenses = (float) Payout::where('status', 'completed')->sum('amount');
        $margin   = $revenue > 0 ? (($revenue - $expenses) / $revenue) * 100 : 0;

        return [
            'total_revenue'        => $revenue,
            'mrr'                  => $this->mrr(),
            'gross_margin'         => round($margin, 1),
            'burn_rate'            => $expenses > 0 ? round($expenses / max(1, now()->month), 0) : 0,
            'cash_runway_months'   => 0,
            'outstanding_invoices' => 0,
        ];
    }

    private function auditSnapshot(): array
    {
        return [
            'events_today'         => DB::table('audit_logs')->whereDate('created_at', today())->count(),
            'flagged_events'       => DB::table('audit_logs')->where('flagged', true)->whereDate('created_at', today())->count(),
            'compliance_score'     => 100,
            'active_violations'    => 0,
            'security_events'      => 0,
            'users_audited'        => DB::table('audit_logs')->whereDate('created_at', today())->distinct('user_id')->count('user_id'),
        ];
    }

    private function vpSalesSnapshot(): array
    {
        return [
            'total_leads'          => $this->newLeadsMtd(),
            'deals_closed_mtd'     => class_exists(Deal::class) ? Deal::where('stage', 'closed_won')->where('created_at', '>=', now()->startOfMonth())->count() : 0,
            'pipeline_value'       => class_exists(Deal::class) ? (float) Deal::whereIn('stage', ['prospecting', 'qualification', 'proposal', 'negotiation'])->sum('value') : 0,
            'win_rate'             => 0,
            'avg_deal_size'        => 0,
            'revenue_target_pct'   => 0,
        ];
    }

    private function vpMarketingSnapshot(): array
    {
        return [
            'active_campaigns'     => $this->activeCampaigns(),
            'new_leads_mtd'        => $this->newLeadsMtd(),
            'email_open_rate'      => 0,
            'subscriber_growth'    => 0,
            'cac'                  => 0,
            'content_published'    => 0,
        ];
    }

    private function vpOpsSnapshot(): array
    {
        return [
            'sessions_live'        => $this->sessionsLive(),
            'open_support_tickets' => $this->openTickets(),
            'hr_headcount'         => User::whereNotIn('role', ['patient', 'therapist'])->count(),
            'on_leave_today'       => 0,
            'open_positions'       => 0,
            'avg_ticket_resolution'=> 0,
        ];
    }

    private function vpProductSnapshot(): array
    {
        return [
            'features_shipped_mtd' => 0,
            'in_development'       => 0,
            'bug_count'            => 0,
            'deploy_frequency'     => 0,
            'system_uptime_pct'    => 99.9,
            'api_error_rate'       => 0,
        ];
    }

    private function financeSnapshot(): array
    {
        return [
            'total_revenue'        => $this->totalRevenue(),
            'outstanding_invoices' => 0,
            'payouts_this_month'   => (float) Payout::where('status', 'completed')->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
            'pending_payouts'      => (float) Payout::where('status', 'pending')->sum('amount'),
        ];
    }

    private function marketingSnapshot(): array
    {
        return [
            'active_campaigns'     => $this->activeCampaigns(),
            'new_leads_mtd'        => $this->newLeadsMtd(),
            'email_open_rate'      => 0,
            'subscriber_count'     => class_exists(Subscriber::class) ? Subscriber::count() : 0,
        ];
    }

    private function salesSnapshot(): array
    {
        return [
            'total_leads'          => $this->newLeadsMtd(),
            'deals_closed_mtd'     => class_exists(Deal::class) ? Deal::where('stage', 'closed_won')->where('created_at', '>=', now()->startOfMonth())->count() : 0,
            'win_rate'             => 0,
        ];
    }

    private function supportSnapshot(): array
    {
        return [
            'open_support_tickets' => $this->openTickets(),
            'avg_first_response_h' => 0,
            'avg_resolution_h'     => 0,
        ];
    }

    private function hrSnapshot(): array
    {
        return [
            'hr_headcount'            => User::whereNotIn('role', ['patient', 'therapist'])->count(),
            'on_leave_today'          => 0,
            'pending_leave_requests'  => 0,
            'open_positions'          => 0,
        ];
    }
}
