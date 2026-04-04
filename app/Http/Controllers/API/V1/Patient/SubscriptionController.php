<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Mail\SubscriptionCancelledEmail;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SubscriptionController extends BaseController
{
    /**
     * Get all available subscription plans.
     */
    public function plans()
    {
        $plans = SubscriptionPlan::where('is_active', true)->get();

        return $this->sendResponse(['plans' => $plans], 'Subscription plans retrieved.');
    }

    /**
     * Get current user's subscription (active, trial, past_due, paused, or cancelled).
     * Returns 404 only when the user has never subscribed.
     */
    public function current()
    {
        /** @var User $user */
        $user = Auth::user();
        $subscription = $user->subscriptions()
            ->whereIn('status', ['active', 'trial', 'past_due', 'paused', 'cancelled'])
            ->with('plan')
            ->latest()
            ->first();

        if (! $subscription) {
            return $this->sendError('No subscription found.', [], 404);
        }

        $data = [
            'uuid'                 => $subscription->uuid,
            'status'               => $subscription->status,
            'plan'                 => $subscription->plan,
            'plan_uuid'            => $subscription->plan?->uuid,
            'starts_at'            => $subscription->current_period_start,
            'ends_at'              => $subscription->cancel_at ?? $subscription->current_period_end,
            'trial_ends_at'        => $subscription->trial_ends_at,
            'cancel_at_period_end' => $subscription->cancel_at !== null,
            'auto_renew'           => (bool) $subscription->auto_renew,
            'paused_at'            => $subscription->paused_at,
            'paused_until'         => $subscription->paused_until,
            'cancelled_at'         => $subscription->cancelled_at,
        ];

        return $this->sendResponse($data, 'Current subscription retrieved.');
    }

    /**
     * S1: Direct subscription creation without payment is disabled.
     * All subscriptions must be created via the payment gateway flow:
     *   POST /payments/subscription/initialize  →  gateway  →  webhook  →  activate
     */
    public function subscribe(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Direct subscription creation is not permitted. Use POST /payments/subscription/initialize to start the payment flow.',
            'error'   => 'use_payment_gateway',
        ], 403);
    }

    public function cancel(Request $request)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $subscription = $user->subscriptions()
            ->whereIn('status', ['active', 'trial', 'paused'])
            ->latest()
            ->first();

        if (! $subscription) {
            return $this->sendError('No active subscription to cancel.', [], 404);
        }

        // Cancel at end of current billing period — NOT immediately.
        // The user retains access until current_period_end.
        $accessUntil = $subscription->current_period_end ?? now()->addDays(30);

        $subscription->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'cancel_at'    => $accessUntil,
            'auto_renew'   => false,
        ]);

        // Revoke unlimited quota if set
        if ($user->has_unlimited_quota) {
            $user->update(['has_unlimited_quota' => false]);
        }

        // Send cancellation confirmation email
        try {
            Mail::to($user->email)->send(new SubscriptionCancelledEmail());
        } catch (\Throwable $e) {
            Log::warning('SubscriptionCancelledEmail failed to send', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return $this->sendResponse([
            'cancellation_date' => now(),
            'access_until'      => $accessUntil,
        ], 'Subscription cancelled. You will retain access until the end of your billing period.');
    }

    public function pause()
    {
        /** @var User $user */
        $user = Auth::user();
        $subscription = $user->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->first();

        if (! $subscription) {
            return $this->sendError('No active subscription to pause.', [], 404);
        }

        $pauseUntil = now()->addMonth();

        $subscription->update([
            'status'       => 'paused',
            'paused_at'    => now(),
            'paused_until' => $pauseUntil,
            'auto_renew'   => false,
        ]);

        // Pausing removes unlimited quota access
        if ($user->has_unlimited_quota) {
            $user->update(['has_unlimited_quota' => false]);
        }

        Log::info('Subscription paused', [
            'user_id'         => $user->id,
            'subscription_id' => $subscription->id,
            'paused_until'    => $pauseUntil,
        ]);

        return $this->sendResponse([
            'paused_until' => $pauseUntil,
            'status'       => 'paused',
        ], 'Subscription paused for one month. It will automatically resume on '.$pauseUntil->format('F j, Y').'.');
    }

    public function resume()
    {
        /** @var User $user */
        $user = Auth::user();
        $subscription = $user->subscriptions()
            ->whereIn('status', ['cancelled', 'paused'])
            ->latest()
            ->first();

        if (! $subscription) {
            return $this->sendError('No paused or cancelled subscription found.', [], 404);
        }

        $plan = $subscription->plan;
        $now = now();
        $periodEnd = $plan && $plan->billing_interval === 'yearly'
            ? $now->copy()->addYear()
            : $now->copy()->addMonth();

        $subscription->update([
            'status'               => 'active',
            'paused_at'            => null,
            'paused_until'         => null,
            'cancel_at'            => null,
            'cancelled_at'         => null,
            'auto_renew'           => true,
            'current_period_start' => $now,
            'current_period_end'   => $periodEnd,
        ]);

        Log::info('Subscription resumed', [
            'user_id'         => $user->id,
            'subscription_id' => $subscription->id,
        ]);

        return $this->sendResponse([
            'status'    => 'active',
            'starts_at' => $now,
            'ends_at'   => $periodEnd,
        ], 'Subscription resumed successfully.');
    }

    /**
     * Last 6 payments — used by the billing history section of the subscription dashboard.
     */
    public function invoices()
    {
        /** @var User $user */
        $user = Auth::user();

        $payments = \App\Models\Payment::where('user_id', $user->id)
            ->with('subscription.plan')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $formatted = $payments->map(function ($payment) {
            return [
                'id'               => $payment->uuid ?? (string) $payment->id,
                'reference'        => $payment->payment_reference,
                'amount'           => $payment->amount,
                'currency'         => $payment->currency ?? 'NGN',
                'status'           => $payment->status,
                'gateway'          => $payment->payment_gateway,
                'plan'             => $payment->subscription?->plan?->name,
                'date'             => ($payment->completed_at ?? $payment->initiated_at ?? $payment->created_at)?->toIso8601String(),
                'is_paid'          => (bool) ($payment->is_paid ?? $payment->status === 'success'),
                'formatted_amount' => $payment->formatted_amount ?? number_format((float) $payment->amount / 100, 2),
            ];
        });

        return $this->sendResponse(['invoices' => $formatted], 'Billing history retrieved.');
    }

    public function payments(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $perPage = (int) $request->input('per_page', 20);

        $payments = \App\Models\Payment::where('user_id', $user->id)
            ->with('subscription.plan')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $formatted = $payments->map(function ($payment) {
            return [
                'id'               => $payment->uuid ?? (string) $payment->id,
                'reference'        => $payment->payment_reference,
                'amount'           => $payment->amount,
                'currency'         => $payment->currency,
                'status'           => $payment->status_display,
                'raw_status'       => $payment->status,
                'gateway'          => $payment->payment_gateway,
                'description'      => $payment->description,
                'plan'             => $payment->subscription?->plan?->name,
                'date'             => $payment->completed_at ?? $payment->initiated_at ?? $payment->created_at,
                'is_paid'          => $payment->is_paid,
                'formatted_amount' => $payment->formatted_amount,
            ];
        });

        return $this->sendResponse([
            'payments'   => $formatted,
            'pagination' => [
                'total'        => $payments->total(),
                'per_page'     => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page'    => $payments->lastPage(),
            ],
        ], 'Payment history retrieved.');
    }
}
