<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Admin\AdminLog;
use App\Models\Payment\Subscription;
use App\Models\Subscription as LegacySubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionUpgradeApprovalController extends BaseController
{
    /**
     * GET /api/v1/admin/subscription-upgrade/requests/stats
     * Return global counts by status and urgent (pending > 72h).
     * Optional: org=university|corporate|faith_ngo to filter by organization type.
     */
    public function stats(Request $request)
    {
        $org = $request->input('org'); // optional
        $base = AdminLog::where('action', 'upgrade_request');
        if ($org) {
            $planType = match ($org) {
                'university' => 'b2b_university',
                'corporate' => 'b2b_corporate',
                'faith_ngo' => 'b2b_faith_ngo',
                default => null,
            };
            if ($planType) {
                $base = $base->where('details->plan_type', $planType);
            }
        }
        $now = now();
        $cutoff = $now->copy()->subHours(72);

        $totalPending = (clone $base)->where('details->status', 'pending')->count();
        $totalApproved = (clone $base)->where('details->status', 'approved')->count();
        $totalDenied = (clone $base)->where('details->status', 'denied')->count();
        $totalUrgent = (clone $base)
            ->where('details->status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->count();

        return $this->sendResponse([
            'pending' => $totalPending,
            'approved' => $totalApproved,
            'denied' => $totalDenied,
            'urgent' => $totalUrgent,
        ], 'Upgrade request stats.');
    }

    /**
     * GET /api/v1/admin/subscription-upgrade/requests
     * List pending or recent requests.
     */
    public function index(Request $request)
    {
        $status = $request->input('status'); // pending|approved|denied|null(all)
        $q = AdminLog::with('user:id,first_name,last_name,email')
            ->where('action', 'upgrade_request');
        if ($status) {
            $q->where('details->status', $status);
        }
        $q->orderByDesc('id');
        $logs = $q->paginate($request->input('per_page', 20));

        $data = $logs->getCollection()->map(function ($log) {
            $req = $log->details ?? [];
            $requester = $log->user;
            $subjectId = $req['subject_user_id'] ?? null;
            $subject = $subjectId ? User::find($subjectId) : null;
            $orgType = null;
            if (! empty($req['plan_type'])) {
                $orgType = match ($req['plan_type']) {
                    'b2b_university' => 'university',
                    'b2b_corporate' => 'corporate',
                    'b2b_faith_ngo' => 'faith_ngo',
                    default => null,
                };
            }

            return [
                'id' => $log->id,
                'requested_at' => $req['requested_at'] ?? $log->created_at,
                'status' => $req['status'] ?? 'pending',
                'requester' => $requester ? trim(($requester->first_name ?? '').' '.($requester->last_name ?? '')) : null,
                'requester_email' => $requester->email ?? null,
                'subject_user' => $subject ? trim(($subject->first_name ?? '').' '.($subject->last_name ?? '')) : null,
                'subject_email' => $subject->email ?? null,
                'plan_uuid' => $req['plan_uuid'] ?? null,
                'plan_slug' => $req['plan_slug'] ?? null,
                'plan_type' => $req['plan_type'] ?? null,
                'org_type' => $orgType,
                'billing_interval' => $req['billing_interval'] ?? null,
                'include_in_revenue' => (bool) ($req['include_in_revenue'] ?? false),
                'comped' => (bool) ($req['comped'] ?? false),
                'reason' => $req['reason'] ?? null,
            ];
        });

        return $this->sendResponse([
            'data' => $data,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ], 'Upgrade requests retrieved.');
    }

    /**
     * POST /api/v1/admin/subscription-upgrade/requests/{id}/approve
     * Performs the upgrade and closes the request.
     */
    public function approve(Request $request, int $id)
    {
        $reqLog = AdminLog::findOrFail($id);
        $details = $reqLog->details ?? [];
        if (($details['status'] ?? 'pending') !== 'pending') {
            return $this->sendError('Request is not pending.');
        }

        $subject = User::findOrFail($details['subject_user_id']);
        $plan = SubscriptionPlan::where('uuid', $details['plan_uuid'])->firstOrFail();
        $interval = $details['billing_interval'] ?? $plan->billing_interval ?? 'monthly';

        $now = now();
        $end = match (strtolower($interval)) {
            'annual', 'yearly', 'year' => (clone $now)->addYear(),
            default => (clone $now)->addMonth(),
        };

        DB::beginTransaction();
        try {
            // Cancel existing
            $subscriptionModel = class_exists(Subscription::class) ? Subscription::class : LegacySubscription::class;
            $subscriptionModel::where('user_id', $subject->id)
                ->whereIn('status', ['active', 'trial', 'past_due'])
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => $now,
                    'cancel_at' => $now,
                    'auto_renew' => false,
                ]);

            $sub = $subscriptionModel::create([
                'user_id' => $subject->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_start' => $now,
                'current_period_end' => $end,
                'auto_renew' => false,
            ]);

            // Mark request approved
            $reqLog->update([
                'details' => array_merge($details, [
                    'status' => 'approved',
                    'approved_by' => $request->user()->id,
                    'approved_at' => now()->toIso8601String(),
                ]),
            ]);

            // Write admin upgrade log
            AdminLog::create([
                'user_id' => $request->user()->id,
                'action' => 'upgrade_subscription',
                'target_type' => $subscriptionModel,
                'target_id' => $sub->id,
                'details' => [
                    'subject_user_id' => $subject->id,
                    'from_plan' => null,
                    'to_plan' => $plan->slug,
                    'to_plan_uuid' => $plan->uuid,
                    'billing_interval' => $interval,
                    'auto_renew' => false,
                    'comped' => (bool) ($details['comped'] ?? false),
                    'include_in_revenue' => (bool) ($details['include_in_revenue'] ?? false),
                    'via_request_id' => $reqLog->id,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->sendResponse(['subscription_uuid' => $sub->uuid ?? null], 'Upgrade approved and applied.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->sendError('Failed to approve request.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/admin/subscription-upgrade/requests/{id}/deny
     */
    public function deny(Request $request, int $id)
    {
        $log = AdminLog::findOrFail($id);
        $details = $log->details ?? [];
        if (($details['status'] ?? 'pending') !== 'pending') {
            return $this->sendError('Request is not pending.');
        }
        $log->update([
            'details' => array_merge($details, [
                'status' => 'denied',
                'approved_by' => $request->user()->id,
                'approved_at' => now()->toIso8601String(),
            ]),
        ]);

        return $this->sendResponse(['id' => $log->id], 'Upgrade request denied.');
    }
}
