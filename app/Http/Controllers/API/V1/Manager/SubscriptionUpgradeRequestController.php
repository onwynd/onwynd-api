<?php

namespace App\Http\Controllers\API\V1\Manager;

use App\Http\Controllers\API\BaseController;
use App\Models\Admin\AdminLog;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SubscriptionUpgradeRequestController extends BaseController
{
    /**
     * POST /api/v1/manager/subscription-upgrade/requests
     * Managers request a user's subscription upgrade for admin approval.
     */
    public function store(Request $request)
    {
        $requiresApproval = (bool) (Setting::where('group', 'features')->where('key', 'manager_upgrade_requires_admin')->value('value') ?? false);
        if (! $requiresApproval) {
            return $this->sendError('Upgrade requests are disabled by settings. Direct upgrades do not require approval.', [], 422);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'plan_uuid' => 'required|string|exists:subscription_plans,uuid',
            'billing_interval' => 'nullable|in:monthly,annual,yearly,year',
            'include_in_revenue' => 'sometimes|boolean',
            'comped' => 'sometimes|boolean',
            'reason' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $plan = SubscriptionPlan::where('uuid', $request->plan_uuid)->firstOrFail();
        $subjectUser = User::findOrFail($request->user_id);

        $log = AdminLog::create([
            'user_id' => Auth::id(),
            'action' => 'upgrade_request',
            'target_type' => User::class,
            'target_id' => $subjectUser->id,
            'details' => [
                'status' => 'pending',
                'subject_user_id' => $subjectUser->id,
                'requested_by' => Auth::id(),
                'plan_uuid' => $plan->uuid,
                'plan_slug' => $plan->slug,
                'plan_type' => $plan->plan_type,
                'billing_interval' => $request->input('billing_interval', $plan->billing_interval ?? 'monthly'),
                'include_in_revenue' => (bool) $request->boolean('include_in_revenue', false),
                'comped' => (bool) $request->boolean('comped', false),
                'reason' => $request->input('reason'),
                'requested_at' => now()->toIso8601String(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse([
            'request_id' => $log->id,
        ], 'Upgrade request submitted for approval.');
    }
}
