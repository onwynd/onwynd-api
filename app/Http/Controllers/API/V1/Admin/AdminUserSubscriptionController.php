<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Admin\AdminLog;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminUserSubscriptionController extends BaseController
{
    public function upgrade(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'plan_uuid' => 'required|string|exists:subscription_plans,uuid',
            'billing_interval' => 'nullable|in:monthly,annual,yearly,year',
            'auto_renew' => 'sometimes|boolean',
            'comped' => 'sometimes|boolean',
            'include_in_revenue' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $plan = SubscriptionPlan::where('uuid', $request->plan_uuid)->firstOrFail();

        $interval = $request->input('billing_interval') ?: ($plan->billing_interval ?? 'monthly');
        $now = Carbon::now();
        $end = match (strtolower($interval)) {
            'annual', 'yearly', 'year' => (clone $now)->addYear(),
            default => (clone $now)->addMonth(),
        };

        DB::beginTransaction();
        try {
            $prev = Subscription::where('user_id', $user->id)
                ->whereIn('status', ['active', 'trial', 'past_due'])
                ->orderByDesc('created_at')
                ->first();

            // Cancel any currently-active subscription
            Subscription::where('user_id', $user->id)
                ->whereIn('status', ['active', 'trial', 'past_due'])
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => $now,
                    'cancel_at' => $now,
                    'auto_renew' => false,
                ]);

            $sub = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_start' => $now,
                'current_period_end' => $end,
                'auto_renew' => (bool) $request->boolean('auto_renew', false) && ! $request->boolean('comped', true),
            ]);

            DB::commit();

            // Admin audit log
            AdminLog::create([
                'user_id' => $request->user()->id,
                'action' => 'upgrade_subscription',
                'target_type' => Subscription::class,
                'target_id' => $sub->id,
                'details' => [
                    'subject_user_id' => $user->id,
                    'from_plan' => $prev?->plan?->slug ?? null,
                    'to_plan' => $plan->slug,
                    'to_plan_uuid' => $plan->uuid,
                    'billing_interval' => $interval,
                    'auto_renew' => (bool) $request->boolean('auto_renew', false),
                    'comped' => (bool) $request->boolean('comped', true),
                    'include_in_revenue' => (bool) $request->boolean('include_in_revenue', false),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->sendResponse([
                'subscription_uuid' => $sub->uuid,
                'user_id' => $user->id,
                'plan' => [
                    'uuid' => $plan->uuid,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'plan_type' => $plan->plan_type,
                    'billing_interval' => $plan->billing_interval,
                ],
                'status' => $sub->status,
                'current_period_start' => $sub->current_period_start,
                'current_period_end' => $sub->current_period_end,
                'auto_renew' => $sub->auto_renew,
                'include_in_revenue' => (bool) $request->boolean('include_in_revenue', false),
            ], 'Subscription upgraded successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->sendError('Failed to upgrade subscription.', ['error' => $e->getMessage()], 500);
        }
    }
}
