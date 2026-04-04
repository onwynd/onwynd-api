<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminSubscriptionListController extends BaseController
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('subscriptions')) {
            return $this->sendResponse([
                'data' => [],
                'stats' => $this->emptyStats(),
            ], 'User subscriptions retrieved.');
        }

        // Latest admin upgrade per subscription (for comped/approver badge)
        $latestUpgradeSub = DB::table('admin_logs')
            ->select(DB::raw('MAX(id) as id'), 'target_id')
            ->where('action', 'upgrade_subscription')
            ->where('target_type', \App\Models\Subscription::class)
            ->groupBy('target_id');

        // Build select columns dynamically based on what exists in the users table
        $selectColumns = [
            'subscriptions.id',
            'subscriptions.user_id',
            'subscriptions.plan_id',
            'subscriptions.status',
            'subscriptions.current_period_start',
            'subscriptions.current_period_end',
            'subscriptions.auto_renew',
            'subscriptions.cancelled_at',
            'subscriptions.created_at as subscribed_at',
            DB::raw("CONCAT(users.first_name, ' ', users.last_name) as user_name"),
            'users.email as user_email',
            'subscription_plans.name as plan_name',
            'subscription_plans.slug as plan_slug',
            'subscription_plans.plan_type',
            'subscription_plans.billing_interval',
            'subscription_plans.price_ngn',
            'subscription_plans.price_usd',
            DB::raw("JSON_EXTRACT(al.details, '$.comped') as comped_flag"),
            DB::raw("CONCAT(approver.first_name, ' ', approver.last_name) as approved_by_name"),
        ];

        // Add student verification columns only if they exist in the database
        if (Schema::hasColumn('users', 'student_verification_status')) {
            $selectColumns[] = 'users.student_verification_status';
        }
        if (Schema::hasColumn('users', 'student_verified_at')) {
            $selectColumns[] = 'users.student_verified_at';
        }
        if (Schema::hasColumn('users', 'student_email')) {
            $selectColumns[] = 'users.student_email';
        }
        if (Schema::hasColumn('users', 'student_id')) {
            $selectColumns[] = 'users.student_id';
        }

        $query = DB::table('subscriptions')
            ->join('users', 'subscriptions.user_id', '=', 'users.id')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->leftJoinSub($latestUpgradeSub, 'latest_upgrade', function ($join) {
                $join->on('latest_upgrade.target_id', '=', 'subscriptions.id');
            })
            ->leftJoin('admin_logs as al', 'al.id', '=', 'latest_upgrade.id')
            ->leftJoin('users as approver', 'approver.id', '=', 'al.user_id')
            ->select($selectColumns);

        if ($request->filled('status')) {
            $query->where('subscriptions.status', $request->status);
        }

        if ($request->filled('plan_id')) {
            $query->where('subscriptions.plan_id', $request->plan_id);
        }

        if ($request->filled('plan_type')) {
            $query->where('subscription_plans.plan_type', $request->plan_type);
        }

        if ($request->filled('search')) {
            $term = '%'.$request->search.'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw("CONCAT(users.first_name, ' ', users.last_name) LIKE ?", [$term])
                    ->orWhere('users.email', 'LIKE', $term);
            });
        }

        $query->orderBy('subscriptions.created_at', 'desc');

        $paginated = $query->paginate($request->input('per_page', 20));

        // ── Global summary stats (always across ALL plan types) ──────────────
        $now = Carbon::now();
        $thirtyDaysOut = $now->copy()->addDays(30);
        $startOfMonth = $now->copy()->startOfMonth();

        $totalActive = DB::table('subscriptions')->where('status', 'active')->count();

        $expiringSoon = DB::table('subscriptions')
            ->where('status', 'active')
            ->whereBetween('current_period_end', [$now, $thirtyDaysOut])
            ->count();

        $cancelledThisMonth = DB::table('subscriptions')
            ->where('status', 'cancelled')
            ->where('cancelled_at', '>=', $startOfMonth)
            ->count();

        // MRR: sum price_ngn for all active subscriptions (monthly-normalised)
        $mrrRaw = DB::table('subscriptions')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('subscriptions.status', 'active')
            ->select(DB::raw('SUM(
                CASE subscription_plans.billing_interval
                    WHEN "monthly"   THEN COALESCE(subscription_plans.price_ngn, 0)
                    WHEN "quarterly" THEN COALESCE(subscription_plans.price_ngn, 0) / 3
                    WHEN "yearly"    THEN COALESCE(subscription_plans.price_ngn, 0) / 12
                    ELSE 0
                END
            ) as mrr'))
            ->value('mrr');

        // ── Per-category active subscriber counts ────────────────────────────
        $planTypes = ['d2c', 'b2b_corporate', 'b2b_university', 'b2b_faith_ngo'];
        $byCategory = [];
        foreach ($planTypes as $type) {
            $byCategory[$type] = DB::table('subscriptions')
                ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
                ->where('subscriptions.status', 'active')
                ->where('subscription_plans.plan_type', $type)
                ->count();
        }

        $stats = [
            'total_active' => $totalActive,
            'expiring_soon' => $expiringSoon,
            'cancelled_this_month' => $cancelledThisMonth,
            'estimated_mrr_ngn' => round($mrrRaw ?? 0),
            'by_category' => $byCategory,
        ];

        return $this->sendResponse([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'stats' => $stats,
        ], 'User subscriptions retrieved.');
    }

    private function emptyStats(): array
    {
        return [
            'total_active' => 0,
            'expiring_soon' => 0,
            'cancelled_this_month' => 0,
            'estimated_mrr_ngn' => 0,
            'by_category' => [
                'd2c' => 0,
                'b2b_corporate' => 0,
                'b2b_university' => 0,
                'b2b_faith_ngo' => 0,
            ],
        ];
    }
}
