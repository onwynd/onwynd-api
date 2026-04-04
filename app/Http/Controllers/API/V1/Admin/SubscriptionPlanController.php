<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionPlanController extends BaseController
{
    /**
     * List all plans with active subscriber counts.
     * Supports ?plan_type=d2c filter and ?active_only=1
     */
    public function index(Request $request)
    {
        $query = SubscriptionPlan::withCount([
            'subscriptions as total_subscribers',
            'subscriptions as active_subscribers' => fn ($q) => $q->where('status', 'active'),
        ]);

        if ($request->filled('plan_type')) {
            $query->where('plan_type', $request->input('plan_type'));
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $plans = $query->orderBy('sort_order')->orderBy('created_at')->get();

        return $this->sendResponse($plans, 'Subscription plans retrieved successfully.');
    }

    /**
     * Create a new subscription plan.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'plan_type' => 'nullable|in:d2c,b2b_corporate,b2b_university,b2b_faith_ngo',
            'price' => 'nullable|numeric|min:0',
            'price_ngn' => 'nullable|numeric|min:0',
            'price_usd' => 'nullable|numeric|min:0',
            'setup_fee_ngn' => 'nullable|numeric|min:0',
            'setup_fee_usd' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'billing_interval' => 'required|in:monthly,quarterly,yearly,one_time',
            'features' => 'nullable|array',
            'features.feature_list' => 'nullable|array',
            'features.feature_list.*' => 'string|max:300',
            'daily_activity_limit' => 'nullable|integer|min:0',
            'ai_message_limit' => 'nullable|integer|min:0',
            'max_sessions' => 'nullable|integer|min:0',
            'trial_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'is_recommended' => 'boolean',
            'best_for' => 'nullable|string|max:300',
            'conversion_target' => 'nullable|integer|min:0|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $input = $request->except(['daily_activity_limit', 'ai_message_limit']);
        $input['uuid'] = (string) Str::uuid();
        $input['slug'] = Str::slug($request->name);

        if (SubscriptionPlan::where('slug', $input['slug'])->exists()) {
            $input['slug'] = $input['slug'].'-'.time();
        }

        $features = $request->input('features', []);
        if (! is_array($features)) {
            $features = [];
        }
        if ($request->filled('daily_activity_limit')) {
            $features['daily_activity_limit'] = (int) $request->input('daily_activity_limit');
        }
        if ($request->filled('ai_message_limit')) {
            $features['ai_message_limit'] = (int) $request->input('ai_message_limit');
        }
        $input['features'] = $features;

        $plan = SubscriptionPlan::create($input);

        return $this->sendResponse($plan->loadCount([
            'subscriptions as total_subscribers',
            'subscriptions as active_subscribers' => fn ($q) => $q->where('status', 'active'),
        ]), 'Subscription plan created successfully.');
    }

    /**
     * Get a single plan with subscriber stats.
     */
    public function show($id)
    {
        $plan = SubscriptionPlan::withCount([
            'subscriptions as total_subscribers',
            'subscriptions as active_subscribers' => fn ($q) => $q->where('status', 'active'),
        ])->find($id);

        if (! $plan) {
            return $this->sendError('Subscription plan not found.');
        }

        return $this->sendResponse($plan, 'Subscription plan retrieved successfully.');
    }

    /**
     * Update a subscription plan.
     */
    public function update(Request $request, $id)
    {
        $plan = SubscriptionPlan::find($id);

        if (! $plan) {
            return $this->sendError('Subscription plan not found.');
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'plan_type' => 'nullable|in:d2c,b2b_corporate,b2b_university,b2b_faith_ngo',
            'price' => 'nullable|numeric|min:0',
            'price_ngn' => 'nullable|numeric|min:0',
            'price_usd' => 'nullable|numeric|min:0',
            'setup_fee_ngn' => 'nullable|numeric|min:0',
            'setup_fee_usd' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'billing_interval' => 'sometimes|required|in:monthly,quarterly,yearly,one_time',
            'features' => 'nullable|array',
            'features.feature_list' => 'nullable|array',
            'features.feature_list.*' => 'string|max:300',
            'daily_activity_limit' => 'nullable|integer|min:0',
            'ai_message_limit' => 'nullable|integer|min:0',
            'max_sessions' => 'nullable|integer|min:0',
            'trial_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'is_recommended' => 'boolean',
            'best_for' => 'nullable|string|max:300',
            'conversion_target' => 'nullable|integer|min:0|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $input = $request->except(['daily_activity_limit', 'ai_message_limit']);

        if ($request->filled('name')) {
            $slug = Str::slug($request->name);
            if (SubscriptionPlan::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $slug.'-'.time();
            }
            $input['slug'] = $slug;
        }

        if ($request->has('features') || $request->filled('daily_activity_limit') || $request->filled('ai_message_limit')) {
            $features = $request->has('features')
                ? ($request->input('features') ?? [])
                : ($plan->features ?? []);
            if (! is_array($features)) {
                $features = [];
            }
            if ($request->filled('daily_activity_limit')) {
                $features['daily_activity_limit'] = (int) $request->input('daily_activity_limit');
            }
            if ($request->filled('ai_message_limit')) {
                $features['ai_message_limit'] = (int) $request->input('ai_message_limit');
            }
            $input['features'] = $features;
        }

        $plan->update($input);

        return $this->sendResponse($plan->loadCount([
            'subscriptions as total_subscribers',
            'subscriptions as active_subscribers' => fn ($q) => $q->where('status', 'active'),
        ]), 'Subscription plan updated successfully.');
    }

    /**
     * Toggle is_active for a plan.
     */
    public function toggleActive($id)
    {
        $plan = SubscriptionPlan::find($id);

        if (! $plan) {
            return $this->sendError('Subscription plan not found.');
        }

        $plan->update(['is_active' => ! $plan->is_active]);

        $status = $plan->is_active ? 'activated' : 'deactivated';

        return $this->sendResponse(['is_active' => $plan->is_active], "Plan {$status} successfully.");
    }

    /**
     * Delete a plan (only if no active subscriptions).
     */
    public function destroy($id)
    {
        $plan = SubscriptionPlan::find($id);

        if (! $plan) {
            return $this->sendError('Subscription plan not found.');
        }

        if ($plan->subscriptions()->where('status', 'active')->exists()) {
            return $this->sendError('Cannot delete a plan with active subscribers. Deactivate it instead.', [], 422);
        }

        $plan->delete();

        return $this->sendResponse([], 'Subscription plan deleted successfully.');
    }
}
