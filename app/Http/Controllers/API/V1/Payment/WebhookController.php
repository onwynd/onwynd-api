<?php

namespace App\Http\Controllers\API\V1\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\FCMService;
use App\Services\PaymentService\PaystackService;
use App\Services\PaymentService\StripeService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Event as StripeEvent;
use Stripe\Webhook as StripeWebhook;

class WebhookController extends Controller
{
    public function handlePaystack(Request $request, PaystackService $paystack): JsonResponse
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('X-Paystack-Signature') ?? '';

            if (! $paystack->verifyWebhookSignature($signature, $payload)) {
                Log::warning('Paystack: Invalid webhook signature');

                return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
            }

            $data = json_decode($payload, true) ?: [];
            $event = $data['event'] ?? '';
            $txData = $data['data'] ?? [];

            $result = $paystack->handleWebhookEvent($data);

            if ($event === 'transfer.success') {
                $this->handlePaystackTransferSuccess($txData);
            }

            if ($event === 'transfer.failed' || $event === 'transfer.reversed') {
                $this->handlePaystackTransferFailed($txData);
            }

            if ($event === 'charge.success') {
                $this->handlePaystackChargeSuccess($txData);
            }

            if ($event === 'subscription.disable') {
                $this->handlePaystackSubscriptionDisable($txData);
            }

            if (in_array($event, ['invoice.payment_failed', 'charge.failed'])) {
                $this->downgradeUserFromWebhook($data);
                $this->sendPaymentFailureNotification($txData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            Log::error('Paystack webhook error', ['message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Webhook processing failed'], 400);
        }
    }

    private function handlePaystackChargeSuccess(array $txData): void
    {
        try {
            $reference = $txData['reference'] ?? null;
            if (! $reference) {
                return;
            }

            $payment = Payment::where('payment_reference', $reference)->first();
            if (! $payment) {
                return;
            }

            if ($payment->status === 'completed') {
                Log::info('R5: charge.success already processed — skipping duplicate', [
                    'reference' => $reference,
                    'payment_id' => $payment->id,
                ]);

                return;
            }

            $metadata = is_array($payment->metadata) ? $payment->metadata : json_decode($payment->metadata ?? '{}', true);
            $type = $metadata['type'] ?? null;

            // Handle subscription payments (including Recovery Program)
            if ($type !== 'recovery_upfront' && $payment->payment_type === 'subscription') {
                $this->handlePaystackSubscriptionChargeSuccess($payment, $metadata);

                return;
            }

            // Handle Recovery Program payments
            if ($type === 'recovery_upfront') {
                $planUuid = $metadata['plan_uuid'] ?? null;
                if (! $planUuid) {
                    Log::warning('R5: recovery_upfront payment missing plan_uuid', ['payment_id' => $payment->id]);

                    return;
                }

                $plan = SubscriptionPlan::where('uuid', $planUuid)
                    ->orWhere('slug', $planUuid)
                    ->first();

                if (! $plan) {
                    Log::warning('R5: Recovery plan not found', ['plan_uuid' => $planUuid]);

                    return;
                }

                $user = User::find($payment->user_id);
                if (! $user) {
                    return;
                }

                $subscription = \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $plan, $user) {
                    $now = Carbon::now();
                    $periodEnd = $now->copy()->addMonths(3);

                    Subscription::where('user_id', $user->id)
                        ->where('status', 'active')
                        ->update(['status' => 'superseded']);

                    $subscription = Subscription::create([
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'status' => 'active',
                        'current_period_start' => $now,
                        'current_period_end' => $periodEnd,
                        'auto_renew' => true,
                    ]);

                    $user->subscription_status = 'active';
                    $user->subscription_plan = $plan->slug;
                    $user->subscription_ends_at = $periodEnd;

                    // FIX 5: Set unlimited quota for plans with no AI message limit
                    $features = is_array($plan->features) ? $plan->features : json_decode($plan->features ?? '{}', true);
                    $aiLimit = $features['ai_message_limit'] ?? 'not_set';
                    if ($aiLimit === null || $aiLimit === 'unlimited' || $aiLimit === 0) {
                        $user->has_unlimited_quota = true;
                    }

                    $user->save();

                    $payment->status = 'completed';
                    $payment->payment_status = 'paid';
                    $payment->completed_at = now();
                    $payment->save();

                    return ['subscription' => $subscription, 'period_end' => $periodEnd];
                });

                Log::info('R5: Recovery subscription activated', [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'subscription_id' => $subscription['subscription']->id,
                    'period_end' => $subscription['period_end']->toIso8601String(),
                ]);

                try {
                    $fcm = app(FCMService::class);
                    $fcm->sendToUser($user->id, [
                        'title' => 'Recovery Program Activated',
                        'body' => 'Your Recovery Program is now active. Your 12-week wellness journey begins today.',
                        'data' => ['type' => 'subscription_activated', 'plan' => $plan->slug],
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('R5: FCM notification failed after Recovery activation', ['error' => $e->getMessage()]);
                }

                return;
            }

            // Handle group session seat payments
            if ($payment->payment_type === 'group_session_seat') {
                \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $txData) {
                    $payment->status         = 'completed';
                    $payment->payment_status = 'paid';
                    $payment->paid_at        = now();
                    $payment->gateway_response = json_encode($txData);
                    $payment->save();

                    $meta           = is_array($payment->metadata) ? $payment->metadata : json_decode($payment->metadata ?? '{}', true);
                    $groupSessionId = $meta['group_session_id'] ?? null;

                    if ($groupSessionId) {
                        \Illuminate\Support\Facades\DB::table('group_session_participants')
                            ->where('group_session_id', $groupSessionId)
                            ->where('user_id', $payment->user_id)
                            ->update([
                                'payment_status' => 'paid',
                                'updated_at'     => now(),
                            ]);
                    }
                });

                Log::info('Group session seat payment completed', [
                    'payment_id' => $payment->id,
                    'reference'  => $reference,
                ]);

                return;
            }

            // Handle regular therapy session payments
            $bookedSession = null;
            \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $txData, &$bookedSession) {
                // Step A: Update the Payment record
                $payment->status = 'completed';
                $payment->payment_status = 'paid';
                $payment->paid_at = now();
                $payment->gateway_response = json_encode($txData);
                $payment->save();

                // Step B: Find and update the TherapySession
                if ($payment->session_id) {
                    $session = \App\Models\TherapySession::find($payment->session_id);
                    if ($session) {
                        $session->payment_status = 'paid';
                        $session->status = 'booked';
                        $session->save();
                        $bookedSession = $session;
                    }
                }

                // Step C: If the payment has an organization_member_id (corporate covered session)
                if ($payment->organization_member_id) {
                    $orgMember = \App\Models\Institutional\OrganizationMember::find($payment->organization_member_id);
                    if ($orgMember) {
                        $orgMember->increment('sessions_used_this_month');

                        $contract = \App\Models\InstitutionalContract::where('institution_user_id', $orgMember->organization->institution_user_id)
                            ->where('status', 'active')
                            ->first();

                        if ($contract) {
                            $contract->increment('sessions_used');
                        }
                    }
                }
            });

            Log::info('Therapy session payment completed', [
                'payment_id' => $payment->id,
                'session_id' => $payment->session_id,
                'reference' => $reference,
            ]);

            // Send confirmation email to patient and notify admin
            if ($bookedSession) {
                $patient = User::find($payment->user_id);
                $therapist = \App\Models\Therapist::with('user')->find($bookedSession->therapist_id);
                $therapistName = $therapist
                    ? 'Dr. '.($therapist->user->first_name ?? '').' '.($therapist->user->last_name ?? '')
                    : 'Your therapist';
                $dateTime = $bookedSession->scheduled_at
                    ? \Carbon\Carbon::parse($bookedSession->scheduled_at)->format('D, d M Y \a\t g:i A')
                    : 'TBD';
                $sessionLink = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://app.onwynd.com')), '/').'/dashboard';

                if ($patient?->email) {
                    try {
                        \Illuminate\Support\Facades\Mail::to($patient->email)->queue(
                            new \App\Mail\AppointmentBookingConfirmation(
                                $patient->first_name ?? $patient->name ?? 'there',
                                $therapistName,
                                $dateTime,
                                $sessionLink,
                            )
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Failed to queue booking confirmation email', ['error' => $e->getMessage()]);
                    }
                }

                // Notify admin
                try {
                    $adminEmail = config('mail.admin_address', env('ADMIN_EMAIL', 'admin@onwynd.com'));
                    \Illuminate\Support\Facades\Mail::to($adminEmail)->queue(
                        new \App\Mail\AdminSessionBookingNotification(
                            $patient,
                            $therapistName,
                            $dateTime,
                            $payment->amount,
                            $payment->currency ?? 'NGN',
                        )
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed to queue admin session booking notification', ['error' => $e->getMessage()]);
                }

                // In-app notification for patient
                try {
                    \App\Models\Notification::create([
                        'user_id' => $payment->user_id,
                        'type'    => 'booking',
                        'title'   => 'Session Confirmed',
                        'message' => "Your session with {$therapistName} on {$dateTime} is confirmed.",
                        'action_url' => '/dashboard',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Failed to create booking confirmation notification', ['error' => $e->getMessage()]);
                }
            }

        } catch (\Throwable $e) {
            Log::error('Failed to process charge.success', ['message' => $e->getMessage(), 'reference' => $reference]);
        }
    }

    public function handleStripe(Request $request, StripeService $stripe): JsonResponse
    {
        try {
            $payload = $request->getContent();
            $sigHeader = $request->header('Stripe-Signature') ?? '';
            $secret = config('services.stripe.webhook.secret');

            if (! $secret) {
                Log::error('Stripe webhook secret not configured');

                return response()->json(['success' => false, 'message' => 'Webhook not configured'], 500);
            }

            /** @var StripeEvent $event */
            $event = StripeWebhook::constructEvent($payload, $sigHeader, $secret);
            $type = $event->type;
            $object = $event->data->object ?? null;

            $stripe->handleWebhookEvent($event);

            match ($type) {
                'invoice.payment_failed' => $this->downgradeUserFromWebhook($event->toArray()),
                'invoice.payment_succeeded' => $this->handleStripeInvoiceSucceeded($object),
                'customer.subscription.updated' => $this->handleStripeSubscriptionUpdated($object),
                'customer.subscription.deleted' => $this->handleStripeSubscriptionDeleted($object),
                'charge.dispute.created' => $this->handleStripeDisputeCreated($object),
                default => null,
            };

            return response()->json(['success' => true, 'message' => 'Webhook processed']);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe: Invalid signature');

            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        } catch (Exception $e) {
            Log::error('Stripe webhook error', ['message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Webhook processing failed'], 400);
        }
    }

    private function handleStripeInvoiceSucceeded(mixed $invoice): void
    {
        try {
            $stripeSubId = $invoice->subscription_id ?? null;
            $email = $invoice->customer_email ?? null;
            $periodEnd = $invoice->lines->data[0]?->period?->end ?? null;

            $user = $this->resolveUserFromStripe(stripeSubId: $stripeSubId, email: $email);
            if (! $user) {
                return;
            }

            $sub = Subscription::where('user_id', $user->id)->where('status', 'active')->latest()->first()
                ?? Subscription::where('user_id', $user->id)->latest()->first();

            if ($sub) {
                $sub->status = 'active';
                $sub->expires_at = $periodEnd ? Carbon::createFromTimestamp($periodEnd) : now()->addMonth();
                $sub->save();
            }
        } catch (Exception $e) {
            Log::error('handleStripeInvoiceSucceeded failed', ['message' => $e->getMessage()]);
        }
    }

    private function handleStripeSubscriptionUpdated(mixed $stripeSub): void
    {
        try {
            $stripeSubId = $stripeSub->id ?? null;
            $status = $stripeSub->status ?? null;
            $periodEnd = $stripeSub->current_period_end ?? null;
            $cancelAt = $stripeSub->cancel_at ?? null;

            $sub = Subscription::where('stripe_subscription_id', $stripeSubId)->latest()->first();
            if (! $sub) {
                return;
            }

            $localStatus = match ($status) {
                'active', 'trialing' => 'active',
                'past_due' => 'past_due',
                'canceled' => 'cancelled',
                default => 'expired',
            };

            $sub->status = $localStatus;
            $sub->expires_at = $periodEnd ? Carbon::createFromTimestamp($periodEnd) : $sub->expires_at;
            if ($cancelAt) {
                $sub->canceled_at = Carbon::createFromTimestamp($cancelAt);
            }
            $sub->save();
        } catch (Exception $e) {
            Log::error('handleStripeSubscriptionUpdated failed', ['message' => $e->getMessage()]);
        }
    }

    private function handleStripeSubscriptionDeleted(mixed $stripeSub): void
    {
        try {
            $stripeSubId = $stripeSub->id ?? null;

            $sub = Subscription::where('stripe_subscription_id', $stripeSubId)->latest()->first();
            if (! $sub) {
                return;
            }

            $sub->status = 'cancelled';
            $sub->canceled_at = now();
            $sub->expires_at = now();
            $sub->auto_renew = false;
            $sub->save();

            if ($sub->user) {
                $sub->user->subscription_status = 'free';
                $sub->user->subscription_ends_at = now();
                $sub->user->has_unlimited_quota = false;
                $sub->user->save();
            }
        } catch (Exception $e) {
            Log::error('handleStripeSubscriptionDeleted failed', ['message' => $e->getMessage()]);
        }
    }

    private function handleStripeDisputeCreated(mixed $dispute): void
    {
        try {
            Log::warning('Stripe dispute created  ops action required', [
                'charge_id' => $dispute->charge ?? 'unknown',
                'amount' => (($dispute->amount ?? 0) / 100).' '.strtoupper($dispute->currency ?? 'usd'),
                'reason' => $dispute->reason ?? 'unknown',
            ]);
        } catch (Exception $e) {
            Log::error('handleStripeDisputeCreated failed', ['message' => $e->getMessage()]);
        }
    }

    private function resolveUserFromStripe(?string $stripeSubId, ?string $email): ?User
    {
        if ($stripeSubId) {
            $sub = Subscription::where('stripe_subscription_id', $stripeSubId)->with('user')->latest()->first();
            if ($sub?->user) {
                return $sub->user;
            }
        }
        if ($email) {
            return User::where('email', $email)->first();
        }

        return null;
    }

    private function handlePaystackTransferSuccess(array $data): void
    {
        try {
            $transferCode = $data['transfer_code'] ?? null;
            $reference = $data['reference'] ?? null;

            $payout = null;
            if ($transferCode) {
                $payout = \App\Models\Payout::where('transfer_code', $transferCode)->first();
            }
            if (! $payout && $reference) {
                $payout = \App\Models\Payout::where('reference', $reference)->first();
            }

            if (! $payout) {
                Log::warning('Paystack transfer.success: payout not found', ['transfer_code' => $transferCode]);

                return;
            }

            $payout->status = 'completed';
            $payout->processed_at = now();
            $payout->save();

            Log::info('Paystack transfer success  payout completed', ['payout_id' => $payout->id]);

            try {
                $notifService = app(\App\Services\NotificationService::class);
                if ($payout->user?->phone) {
                    $notifService->sendMessage(
                        $payout->user->phone,
                        'Your Onwynd payout of '.number_format($payout->amount).' has been completed successfully.'
                    );
                }
            } catch (\Throwable $notifErr) {
                Log::warning('Paystack transfer.success: notification failed', ['error' => $notifErr->getMessage()]);
            }
        } catch (\Throwable $e) {
            Log::error('handlePaystackTransferSuccess error', ['message' => $e->getMessage()]);
        }
    }

    private function handlePaystackTransferFailed(array $data): void
    {
        try {
            $transferCode = $data['transfer_code'] ?? null;
            $reference = $data['reference'] ?? null;
            $reason = $data['gateway_response'] ?? 'Transfer failed';

            $payout = null;
            if ($transferCode) {
                $payout = \App\Models\Payout::where('transfer_code', $transferCode)->first();
            }
            if (! $payout && $reference) {
                $payout = \App\Models\Payout::where('reference', $reference)->first();
            }

            if (! $payout) {
                Log::warning('Paystack transfer.failed: payout not found', ['transfer_code' => $transferCode]);

                return;
            }

            $payout->status = 'failed';
            $payout->failure_reason = $reason;
            $payout->save();

            Log::info('Paystack transfer failed  payout marked failed', ['payout_id' => $payout->id, 'reason' => $reason]);
        } catch (\Throwable $e) {
            Log::error('handlePaystackTransferFailed error', ['message' => $e->getMessage()]);
        }
    }

    private function handlePaystackSubscriptionChargeSuccess(Payment $payment, array $metadata): void
    {
        try {
            $planUuid = $metadata['plan_uuid'] ?? null;
            $billingPeriod = $metadata['billing_period'] ?? 'monthly';

            if (! $planUuid) {
                Log::warning('P3: Subscription charge.success missing plan_uuid', ['payment_id' => $payment->id]);

                return;
            }

            $plan = SubscriptionPlan::where('uuid', $planUuid)
                ->orWhere('id', $metadata['plan_id'] ?? null)
                ->first();

            if (! $plan) {
                Log::warning('P3: Subscription plan not found', ['plan_uuid' => $planUuid]);

                return;
            }

            $user = User::find($payment->user_id);
            if (! $user) {
                return;
            }

            \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $plan, $user, $billingPeriod) {
                $now = Carbon::now();
                $periodEnd = $billingPeriod === 'annual'
                    ? $now->copy()->addYear()
                    : $now->copy()->addMonth();

                Subscription::where('user_id', $user->id)
                    ->whereIn('status', ['active', 'trial'])
                    ->update(['status' => 'superseded']);

                Subscription::create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'current_period_start' => $now,
                    'current_period_end' => $periodEnd,
                    'auto_renew' => true,
                ]);

                $user->subscription_status = 'active';
                $user->subscription_plan = $plan->slug;
                $user->subscription_ends_at = $periodEnd;

                // FIX 5: Set unlimited quota flag for plans with unlimited AI messages
                $features = is_array($plan->features) ? $plan->features : json_decode($plan->features ?? '{}', true);
                $aiLimit = $features['ai_message_limit'] ?? 'not_set';
                if ($aiLimit === null || $aiLimit === 'unlimited' || $aiLimit === 0) {
                    $user->has_unlimited_quota = true;
                }

                $user->save();

                $payment->status = 'completed';
                $payment->payment_status = 'paid';
                $payment->completed_at = now();
                $payment->save();
            });

            Log::info('P3: Subscription activated from charge.success', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'billing_period' => $billingPeriod,
            ]);
        } catch (\Throwable $e) {
            Log::error('P3: Failed to activate subscription from charge.success', ['message' => $e->getMessage()]);
        }
    }

    private function sendPaymentFailureNotification(array $txData): void
    {
        try {
            $email = $txData['customer']['email'] ?? $txData['customer_email'] ?? null;
            $reference = $txData['reference'] ?? null;
            $user = null;

            if ($reference) {
                $payment = Payment::where('payment_reference', $reference)->first();
                if ($payment?->user) {
                    $user = $payment->user;
                }
            }
            if (! $user && $email) {
                $user = User::where('email', $email)->first();
            }
            if (! $user) {
                return;
            }

            $fcm = app(FCMService::class);
            $fcm->sendToUser($user->id, [
                'title' => 'Payment Failed',
                'body' => 'Your payment could not be processed. Please update your payment method to retain access.',
                'data' => ['type' => 'payment_failed', 'reference' => $reference],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Payment failure FCM notification failed', ['error' => $e->getMessage()]);
        }
    }

    private function handlePaystackSubscriptionDisable(array $data): void
    {
        try {
            $customerCode = $data['customer']['customer_code'] ?? null;
            $email = $data['customer']['email'] ?? null;
            $subscriptionCode = $data['subscription_code'] ?? null;

            $user = null;
            if ($email) {
                $user = User::where('email', $email)->first();
            }
            if (! $user) {
                Log::warning('Paystack subscription.disable: user not found', ['email' => $email, 'code' => $subscriptionCode]);

                return;
            }

            Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'canceled_at' => now()]);

            $user->subscription_status = 'free';
            $user->subscription_ends_at = now();
            $user->has_unlimited_quota = false;
            $user->save();

            Log::info('Paystack subscription.disable: subscription cancelled', ['user_id' => $user->id, 'code' => $subscriptionCode]);
        } catch (\Throwable $e) {
            Log::error('handlePaystackSubscriptionDisable failed', ['message' => $e->getMessage()]);
        }
    }

    private function downgradeUserFromWebhook(array $payload): void
    {
        try {
            $email = $payload['data']['customer']['email'] ?? $payload['data']['customer_email'] ?? null;
            $reference = $payload['data']['reference'] ?? $payload['data']['tx_ref'] ?? $payload['data']['invoice_code'] ?? null;
            $user = null;

            if ($reference) {
                $payment = Payment::where('payment_reference', $reference)->orWhere('gateway_payment_id', $reference)->latest()->first();
                if ($payment?->user) {
                    $user = $payment->user;
                }
            }
            if (! $user && $email) {
                $user = User::where('email', $email)->first();
            }
            if (! $user) {
                Log::warning('Webhook downgrade: user not found', ['reference' => $reference, 'email' => $email]);

                return;
            }

            $graceEndsAt = now()->addDays(3);

            $user->subscription_status = 'past_due';
            $user->subscription_ends_at = $graceEndsAt;
            $user->has_unlimited_quota = false;
            $user->save();

            Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'past_due']);

            \App\Jobs\DowngradeExpiredGraceJob::dispatch($user->id)->delay($graceEndsAt);

            Log::info('User entered payment grace period — will downgrade in 3 days', [
                'user_id' => $user->id,
                'grace_ends_at' => $graceEndsAt->toIso8601String(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to set grace period from webhook', ['message' => $e->getMessage()]);
        }
    }
}
