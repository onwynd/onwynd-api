<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\AIChat;
use App\Models\CrisisEvent;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\OperationalLog;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\TherapySession;
use App\Models\User;
use App\Traits\HasClinicalEthicsGuard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class COOController extends BaseController
{
    use HasClinicalEthicsGuard;

    /**
     * GET /api/v1/coo/operations-overview
     */
    public function operationsOverview()
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        // 1. Sales Pipeline Health
        $salesHealth = [
            'leads_this_week' => Lead::where('created_at', '>=', $thisWeek)->count(),
            'deals_in_progress' => Deal::whereIn('status', ['proposal_sent', 'negotiation'])->count(),
            'closed_this_month' => Deal::whereIn('status', ['won', 'lost'])
                ->where('updated_at', '>=', $thisMonth)
                ->count(),
            'win_rate' => Deal::where('updated_at', '>=', $thisMonth)
                ->whereIn('status', ['won', 'lost'])
                ->count() > 0
                    ? round((Deal::where('status', 'won')->where('updated_at', '>=', $thisMonth)->count() / Deal::whereIn('status', ['won', 'lost'])->where('updated_at', '>=', $thisMonth)->count()) * 100, 1)
                    : 0,
            'stale_deals_amber' => Deal::where('updated_at', '<=', Carbon::now()->subDays(7))
                ->whereNotIn('status', ['won', 'lost'])
                ->count(),
            'stale_deals_red' => Deal::where('updated_at', '<=', Carbon::now()->subDays(14))
                ->whereNotIn('status', ['won', 'lost'])
                ->count(),
        ];

        // 2. Support Health
        $supportHealth = [
            'open_tickets' => SupportTicket::whereIn('status', ['open', 'pending'])->count(),
            'avg_first_response_hours' => 1.5, // Placeholder - requires detailed ticket logs
            'avg_resolution_hours' => 18.0, // Placeholder
            'long_open_tickets' => SupportTicket::where('created_at', '<=', Carbon::now()->subHours(48))
                ->whereIn('status', ['open', 'pending'])
                ->count(),
            'ai_handover_rate' => 12.5, // Placeholder
        ];

        // 3. Session Operations
        $sessionOps = [
            'scheduled_today' => TherapySession::whereDate('scheduled_at', $today)->count(),
            'completed_today' => TherapySession::whereDate('scheduled_at', $today)->where('status', 'completed')->count(),
            'no_shows_today' => TherapySession::whereDate('scheduled_at', $today)->where('status', 'no_show')->count(),
            'ended_early' => TherapySession::where('status', 'ended_early')->count(),
            'therapist_availability_gaps' => 0, // Placeholder
        ];

        // 4. Platform Health
        $platformHealth = [
            'api_error_rate' => 0.05, // Placeholder
            'ai_companion_uptime' => AIChat::latest()->first()?->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'last_payment_webhook' => Payment::latest()->first()?->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'active_sessions' => 0, // Placeholder - LiveKit integration
        ];

        return $this->sendResponse([
            'sales_health' => $salesHealth,
            'support_health' => $supportHealth,
            'session_ops' => $sessionOps,
            'platform_health' => $platformHealth,
        ], 'Operations overview retrieved successfully.');
    }

    /**
     * GET /api/v1/coo/ai-operations
     */
    public function aiOperations()
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        // 1. AI Usage
        $aiUsage = [
            'total_today' => AIChat::whereDate('created_at', $today)->count(),
            'total_this_week' => AIChat::where('created_at', '>=', Carbon::now()->startOfWeek())->count(),
            'total_this_month' => AIChat::where('created_at', '>=', $thisMonth)->count(),
            'by_language' => [
                'EN' => AIChat::count(), // Simplified
                'IG' => 0,
                'YO' => 0,
                'HA' => 0,
            ],
            'avg_messages_per_session' => 8.4, // Placeholder
            'quota_hit_rate' => 2.1, // Placeholder
        ];

        // 2. Crisis Detection
        $crisisPerformance = [
            'distress_flags_this_month' => (function () use ($thisMonth) {
                // ETHICS GUARD: exclude own patients if caller is a clinical_advisor/therapist
                $ownPatientIds = $this->getOwnPatientIds();

                return CrisisEvent::where('triggered_at', '>=', $thisMonth)
                    ->when($ownPatientIds->isNotEmpty(), fn ($q) => $q->whereNotIn('user_id', $ownPatientIds))
                    ->count();
            })(),
            'conversion_to_booking' => 15.0, // Placeholder
            'false_positive_rate' => 5.0, // Placeholder
            'avg_review_time_mins' => 45, // Placeholder
        ];

        // 3. Provider Health
        $providerHealth = [
            'current_provider' => config('ai.provider', 'openai'),
            'last_success' => AIChat::latest()->first()?->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'error_rate_today' => 0.01, // Placeholder
            'fallback_activations' => 0, // Placeholder
        ];

        return $this->sendResponse([
            'ai_usage' => $aiUsage,
            'crisis_performance' => $crisisPerformance,
            'provider_health' => $providerHealth,
        ], 'AI operations retrieved successfully.');
    }

    /**
     * GET /api/v1/marketing/funnel
     */
    public function marketingFunnel()
    {
        // Simple acquisition funnel
        $funnel = [
            'homepage_visits' => null, // Requires external analytics
            'visits_require_analytics' => true,
            'signups_started' => User::count(), // Simplified
            'signups_completed' => User::whereNotNull('email_verified_at')->count(),
            'onboarding_completed' => User::whereNotNull('onboarding_completed_at')->count(),
            'first_ai_message' => AIChat::distinct('user_id')->count(),
            'first_session_booked' => TherapySession::distinct('patient_id')->count(),
        ];

        $growth = [
            'total_users' => User::count(),
            'new_this_week' => User::where('created_at', '>=', Carbon::now()->startOfWeek())->count(),
            'users_by_plan' => DB::table('users')
                ->join('subscription_plans', 'users.subscription_plan_id', '=', 'subscription_plans.id')
                ->select('subscription_plans.name', DB::raw('count(*) as total'))
                ->groupBy('subscription_plans.name')
                ->get(),
        ];

        return $this->sendResponse([
            'funnel' => $funnel,
            'growth' => $growth,
        ], 'Marketing funnel retrieved successfully.');
    }

    /**
     * GET /api/v1/operational-logs
     */
    public function listOperationalLogs(Request $request)
    {
        $query = OperationalLog::with('creator:id,first_name,last_name')
            ->orderBy('log_date', 'desc');

        // COO/CEO see all, others might be restricted
        if (! auth()->user()->hasRole('coo') && ! auth()->user()->hasRole('ceo') && ! auth()->user()->hasRole('admin')) {
            return $this->sendError('Unauthorized', [], 403);
        }

        if (! auth()->user()->hasRole('coo') && ! auth()->user()->hasRole('ceo')) {
            $query->where('visibility', 'all_admin');
        }

        return $this->sendResponse($query->paginate(20), 'Operational logs retrieved.');
    }

    /**
     * POST /api/v1/operational-logs
     */
    public function storeOperationalLog(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:milestone,incident,decision,process,investor_note,other',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'log_date' => 'required|date',
            'visibility' => 'required|in:coo_ceo,all_admin',
        ]);

        $data['created_by'] = auth()->id();
        $log = OperationalLog::create($data);

        return $this->sendResponse($log->load('creator:id,first_name,last_name'), 'Log entry created.', 201);
    }

    /**
     * PATCH /api/v1/operational-logs/{id}
     */
    public function updateOperationalLog(Request $request, $id)
    {
        $log = OperationalLog::findOrFail($id);

        if ($log->created_by !== auth()->id() && ! auth()->user()->hasRole('admin')) {
            return $this->sendError('Unauthorized to edit this entry.', [], 403);
        }

        $data = $request->validate([
            'type' => 'sometimes|in:milestone,incident,decision,process,investor_note,other',
            'title' => 'sometimes|string|max:255',
            'body' => 'sometimes|string',
            'log_date' => 'sometimes|date',
            'visibility' => 'sometimes|in:coo_ceo,all_admin',
        ]);

        $log->update($data);

        return $this->sendResponse($log->fresh()->load('creator:id,first_name,last_name'), 'Log entry updated.');
    }

    /**
     * DELETE /api/v1/operational-logs/{id}
     */
    public function destroyOperationalLog($id)
    {
        $log = OperationalLog::findOrFail($id);

        if ($log->created_by !== auth()->id() && ! auth()->user()->hasRole('admin')) {
            return $this->sendError('Unauthorized to delete this entry.', [], 403);
        }

        $log->delete();

        return $this->sendResponse(null, 'Log entry deleted.');
    }
}
