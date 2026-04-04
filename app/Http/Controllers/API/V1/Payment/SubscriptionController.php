<?php

namespace App\Http\Controllers\API\V1\Payment;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function upgrade(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'nullable|integer|exists:subscription_plans,id',
            'plan_slug' => 'nullable|string|exists:subscription_plans,slug',
            'auto_renew' => 'nullable|boolean',
        ]);

        try {
            $user = $request->user();
            $plan = null;
            if ($request->filled('plan_id')) {
                $plan = SubscriptionPlan::findOrFail($request->integer('plan_id'));
            } elseif ($request->filled('plan_slug')) {
                $plan = SubscriptionPlan::where('slug', $request->string('plan_slug'))->firstOrFail();
            }

            if (! $plan || ! $plan->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or inactive plan',
                ], 400);
            }

            $now = Carbon::now();
            $periodStart = $now->copy();
            $periodEnd = match ($plan->billing_interval) {
                'monthly' => $now->copy()->addMonth(),
                'yearly' => $now->copy()->addYear(),
                default => $now->copy()->addMonth(),
            };

            $subscription = Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->orderByDesc('current_period_end')
                ->first();

            if ($subscription) {
                $subscription->update([
                    'plan_id' => $plan->id,
                    'current_period_start' => $periodStart,
                    'current_period_end' => $periodEnd,
                    'auto_renew' => $request->boolean('auto_renew', true),
                ]);
            } else {
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'current_period_start' => $periodStart,
                    'current_period_end' => $periodEnd,
                    'auto_renew' => $request->boolean('auto_renew', true),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Subscription upgraded',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'plan' => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'slug' => $plan->slug,
                        'price' => $plan->price,
                        'currency' => $plan->currency,
                        'max_sessions' => $plan->max_sessions,
                    ],
                    'current_period_start' => $periodStart->toIso8601String(),
                    'current_period_end' => $periodEnd->toIso8601String(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Subscription upgrade failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Upgrade failed: '.$e->getMessage(),
            ], 400);
        }
    }

    public function resume(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $subscription = Subscription::where('user_id', $user->id)
                ->whereIn('status', ['cancelled', 'paused'])
                ->orderByDesc('updated_at')
                ->first();

            if (! $subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No cancelled or paused subscription found',
                ], 404);
            }

            $now = Carbon::now();
            $plan = $subscription->plan;
            $periodEnd = match ($plan?->billing_interval) {
                'yearly' => $now->copy()->addYear(),
                default => $now->copy()->addMonth(),
            };

            $subscription->update([
                'status' => 'active',
                'current_period_start' => $now,
                'current_period_end' => $periodEnd,
                'ends_at' => null,
            ]);

            Log::info('Subscription resumed', ['user_id' => $user->id, 'subscription_id' => $subscription->id]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription resumed successfully',
                'data' => $subscription->fresh()->load('plan'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Subscription resume failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Resume failed: '.$e->getMessage(),
            ], 400);
        }
    }

    public function changePlan(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required_without:plan_uuid',
            'plan_uuid' => 'required_without:plan_id|string',
        ]);

        try {
            $user = $request->user();

            $identifier = $request->input('plan_id') ?? $request->input('plan_uuid');
            $plan = SubscriptionPlan::where('id', $identifier)
                ->orWhere('uuid', $identifier)
                ->orWhere('slug', $identifier)
                ->where('is_active', true)
                ->firstOrFail();

            $subscription = Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->orderByDesc('current_period_end')
                ->first();

            if (! $subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found',
                ], 404);
            }

            $now = Carbon::now();
            $periodEnd = match ($plan->billing_interval) {
                'yearly' => $now->copy()->addYear(),
                default => $now->copy()->addMonth(),
            };

            $subscription->update([
                'plan_id' => $plan->id,
                'current_period_start' => $now,
                'current_period_end' => $periodEnd,
            ]);

            Log::info('Subscription plan changed', ['user_id' => $user->id, 'plan_id' => $plan->id]);

            return response()->json([
                'success' => true,
                'message' => 'Plan changed successfully',
                'data' => $subscription->fresh()->load('plan'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Subscription plan change failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Plan change failed: '.$e->getMessage(),
            ], 400);
        }
    }
}
