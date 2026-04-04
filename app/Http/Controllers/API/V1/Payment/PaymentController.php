<?php

namespace App\Http\Controllers\API\V1\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\InitiatePaymentRequest;
use App\Http\Requests\Payment\RefundPaymentRequest;
use App\Http\Requests\Payment\VerifyPaymentRequest;
use App\Models\Payment;
use App\Models\Payment\Subscription as PaymentSubscription;
use App\Models\Setting;
use App\Models\Subscription as LegacySubscription;
use App\Models\SubscriptionPlan;
use App\Models\GroupSession;
use App\Models\TherapySession;
use App\Services\PaymentService\PaymentProcessor;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private $paymentProcessor;

    public function __construct(PaymentProcessor $paymentProcessor)
    {
        $this->paymentProcessor = $paymentProcessor;
    }

    /**
     * B4: Initialize a session payment with server-side platform booking fee enforcement.
     *
     * Fee-exempt plans: premium, recovery — they pay zero platform fee.
     * All other plans (basic, freemium) pay the config-driven fee ON TOP of the therapist rate.
     * The therapist still receives 80% of their own rate; the platform fee is an additional
     * charge to the patient and NEVER reduces the therapist share.
     *
     * SECURITY: The fee exemption is derived from the user's ACTIVE subscription plan
     * server-side. Any client-supplied platform_booking_fee_ngn is ignored entirely.
     *
     * POST /api/v1/payments/session/initialize
     */
    public function initializeSessionPayment(Request $request): JsonResponse
    {
        $request->validate([
            'session_uuid' => 'required|string',
            'currency' => 'nullable|string|in:NGN,USD',
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
        ]);

        try {
            $user = Auth::user();
            if (! $user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $session = TherapySession::where('uuid', $request->session_uuid)
                ->where('patient_id', $user->id)
                ->firstOrFail();

            // Double charge guard: check if session is already paid
            if ($session->payment_status === 'paid') {
                return response()->json([
                    'success' => false,
                    'error' => 'This session has already been paid for.',
                    'session_id' => $session->uuid,
                ], 409);
            }

            // Check for existing pending payment
            $existingPayment = Payment::where('session_id', $session->id)
                ->where('status', 'pending')
                ->first();

            if ($existingPayment) {
                // Return the existing payment reference instead of creating a new one
                return response()->json([
                    'success' => true,
                    'message' => 'Session payment already initialized',
                    'data' => [
                        'authorization_url' => $existingPayment->authorization_url,
                        'access_code' => $existingPayment->access_code,
                        'reference' => $existingPayment->reference,
                        'gateway' => $existingPayment->payment_gateway,
                        'amount' => $existingPayment->amount,
                        'currency' => $existingPayment->currency,
                        'reused' => true,
                    ],
                ], 200);
            }

            $currency = $request->input('currency', 'NGN');
            $therapistRate = (float) ($session->session_rate ?? 0);

            // Derive platform fee server-side — client cannot override this.
            $platformFee = $this->derivePlatformFee($user);

            // Apply VAT server-side from Financial Settings (group=financial, key=vat_rate).
            // VAT is calculated on (therapist rate + platform fee). 0 = VAT-exempt.
            $vatRate = (float) (Setting::where('group', 'financial')->where('key', 'vat_rate')->value('value') ?? 0);
            $vatAmount = round(($therapistRate + $platformFee) * ($vatRate / 100), 2);
            $totalAmount = $therapistRate + $platformFee + $vatAmount;

            $payment = Payment::create([
                'user_id' => $user->id,
                'amount' => $totalAmount,
                'session_rate' => $therapistRate,
                'currency' => $currency,
                'payment_type' => 'session_booking',
                'description' => 'Session booking'.($platformFee > 0 ? " (incl. \u{20A6}{$platformFee} platform fee)" : '').($vatAmount > 0 ? " + \u{20A6}{$vatAmount} VAT" : ''),
                'metadata' => [
                    'session_uuid' => $session->uuid,
                    'therapist_id' => $session->therapist_id,
                    'therapist_rate' => $therapistRate,
                    'platform_fee' => $platformFee,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $vatAmount,
                    'success_url' => $request->success_url,
                    'cancel_url' => $request->cancel_url,
                ],
                'status' => 'draft',
                'payment_status' => 'pending',
            ]);

            $result = $this->paymentProcessor->processPayment($payment, [
                'customer_name' => $user->full_name,
                'customer_email' => $user->email,
                'description' => "Therapy session {$currency}{$totalAmount}",
            ]);

            // Save authorization_url and access_code from the gateway response
            if (isset($result['authorization_url']) || isset($result['access_code'])) {
                $payment->update([
                    'authorization_url' => $result['authorization_url'] ?? null,
                    'access_code' => $result['access_code'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Session payment initialized',
                'data' => [
                    'authorization_url' => $result['authorization_url'] ?? null,
                    'access_code' => $result['access_code'] ?? null,
                    'reference' => $result['reference'],
                    'gateway' => $result['gateway'],
                    'amount' => $totalAmount,
                    'therapist_rate' => $therapistRate,
                    'platform_fee' => $platformFee,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $vatAmount,
                    'currency' => $currency,
                ],
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Session not found.'], 404);
        } catch (Exception $e) {
            Log::error('Session payment initialization failed', [
                'user_id' => Auth::id(),
                'session_uuid' => $request->session_uuid,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize session payment: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Initialize payment for a group session seat.
     *
     * POST /api/v1/payments/group-session/initialize
     * Payload: { "group_session_uuid": "...", "currency": "NGN", "success_url": "...", "cancel_url": "..." }
     */
    public function initializeGroupSessionPayment(Request $request): JsonResponse
    {
        $request->validate([
            'group_session_uuid' => 'required|string',
            'currency'           => 'nullable|string|in:NGN,USD',
            'success_url'        => 'nullable|url',
            'cancel_url'         => 'nullable|url',
        ]);

        try {
            $user = Auth::user();
            if (! $user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $session = GroupSession::where('uuid', $request->group_session_uuid)->firstOrFail();

            // Only sessions with a price require payment
            if ($session->is_org_covered || $session->price_per_seat_kobo <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This session does not require payment.',
                ], 422);
            }

            // Confirm the user has already joined (payment_status=pending participant record)
            $participant = \DB::table('group_session_participants')
                ->where('group_session_id', $session->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $participant) {
                return response()->json(['success' => false, 'message' => 'You must join the session before paying.'], 422);
            }

            if (($participant->payment_status ?? '') === 'paid') {
                return response()->json(['success' => false, 'message' => 'Seat already paid for.'], 409);
            }

            // Check for an existing pending payment to avoid duplicates
            $existingPayment = Payment::where('user_id', $user->id)
                ->where('payment_type', 'group_session_seat')
                ->whereJsonContains('metadata->group_session_uuid', $session->uuid)
                ->where('status', 'pending')
                ->first();

            if ($existingPayment) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment already initialized',
                    'data'    => [
                        'authorization_url' => $existingPayment->authorization_url,
                        'access_code'       => $existingPayment->access_code,
                        'reference'         => $existingPayment->reference,
                        'gateway'           => $existingPayment->payment_gateway,
                        'amount'            => $existingPayment->amount,
                        'currency'          => $existingPayment->currency,
                        'reused'            => true,
                    ],
                ]);
            }

            $currency    = $request->input('currency', 'NGN');
            $amountNgn   = round($session->price_per_seat_kobo / 100, 2);

            $payment = Payment::create([
                'user_id'        => $user->id,
                'amount'         => $amountNgn,
                'currency'       => $currency,
                'payment_type'   => 'group_session_seat',
                'description'    => "Group session seat: {$session->title}",
                'metadata'       => [
                    'group_session_uuid' => $session->uuid,
                    'group_session_id'   => $session->id,
                    'success_url'        => $request->success_url,
                    'cancel_url'         => $request->cancel_url,
                ],
                'status'         => 'draft',
                'payment_status' => 'pending',
            ]);

            $result = $this->paymentProcessor->processPayment($payment, [
                'customer_name'  => $user->full_name,
                'customer_email' => $user->email,
                'description'    => "Group session seat — {$session->title}",
            ]);

            if (isset($result['authorization_url']) || isset($result['access_code'])) {
                $payment->update([
                    'authorization_url' => $result['authorization_url'] ?? null,
                    'access_code'       => $result['access_code'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Group session payment initialized',
                'data'    => [
                    'authorization_url' => $result['authorization_url'] ?? null,
                    'access_code'       => $result['access_code'] ?? null,
                    'reference'         => $result['reference'],
                    'gateway'           => $result['gateway'],
                    'amount'            => $amountNgn,
                    'currency'          => $currency,
                ],
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Group session not found.'], 404);
        } catch (Exception $e) {
            Log::error('Group session payment initialization failed', [
                'user_id'            => Auth::id(),
                'group_session_uuid' => $request->group_session_uuid,
                'message'            => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize payment: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * R5: Initialize a one-time (non-recurring) Paystack payment for the Recovery Program.
     *
     * SECURITY: The amount is derived server-side from the plan_uuid lookup.
     * The client CANNOT supply an amount — any client-supplied amount field is rejected.
     * On webhook confirmation (charge.success with metadata.type === 'recovery_upfront'),
     * the backend automatically activates the Recovery subscription.
     *
     * POST /api/v1/payments/one-time/initialize
     */
    public function initializeOneTimePayment(Request $request): JsonResponse
    {
        $request->validate([
            'currency' => 'nullable|string|in:NGN,USD',
            'description' => 'nullable|string|max:255',
            'plan_uuid' => 'required|string',
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
            'metadata' => 'nullable|array',
        ]);

        try {
            $user = Auth::user();
            if (! $user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $currency = $request->input('currency', 'NGN');
            $planUuid = $request->input('plan_uuid');

            // Amount MUST come from the authoritative plan record.
            // Client-supplied amounts are silently ignored.
            $plan = SubscriptionPlan::where('uuid', $planUuid)
                ->orWhere('slug', $planUuid)
                ->where('is_active', true)
                ->firstOrFail();

            // Double charge guard: check if user already has an active subscription for this plan
            // One-time payments for Recovery Program (metadata type recovery_upfront)
            $activeSub = PaymentSubscription::where('user_id', $user->id)
                ->where('plan_id', $plan->id)
                ->active()
                ->first();

            if ($activeSub) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active subscription for this plan.',
                    'error' => 'already_subscribed',
                ], 409);
            }

            // Check for existing pending one-time payment for this plan
            $existingPayment = Payment::where('user_id', $user->id)
                ->where('payment_type', 'subscription')
                ->where('status', 'pending')
                ->whereJsonContains('metadata->type', 'recovery_upfront')
                ->whereJsonContains('metadata->plan_id', $plan->id)
                ->first();

            if ($existingPayment) {
                return response()->json([
                    'success' => true,
                    'message' => 'One-time payment already initialized',
                    'data' => [
                        'authorization_url' => $existingPayment->authorization_url,
                        'access_code' => $existingPayment->access_code,
                        'reference' => $existingPayment->reference,
                        'gateway' => $existingPayment->payment_gateway,
                        'amount' => $existingPayment->amount,
                        'currency' => $existingPayment->currency,
                        'reused' => true,
                    ],
                ], 200);
            }

            $amount = $currency === 'USD'
                ? (float) ($plan->price_usd ?? $plan->price)
                : (float) ($plan->price_ngn ?? $plan->price);

            $description = $request->input('description', "Recovery Program — {$plan->name}");

            $payment = Payment::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'currency' => $currency,
                'payment_type' => 'subscription',
                'description' => $description,
                'metadata' => array_merge(
                    $request->input('metadata', []),
                    [
                        'type' => 'recovery_upfront',
                        'plan_uuid' => $planUuid,
                        'plan_id' => $plan->id,
                        'success_url' => $request->success_url,
                        'cancel_url' => $request->cancel_url,
                    ]
                ),
                'status' => 'draft',
                'payment_status' => 'pending',
            ]);

            $result = $this->paymentProcessor->processPayment($payment, [
                'customer_name' => $user->full_name,
                'customer_email' => $user->email,
                'description' => $description,
            ]);

            // Save authorization_url and access_code from the gateway response
            if (isset($result['authorization_url']) || isset($result['access_code'])) {
                $payment->update([
                    'authorization_url' => $result['authorization_url'] ?? null,
                    'access_code' => $result['access_code'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'One-time payment initialized',
                'data' => [
                    'authorization_url' => $result['authorization_url'] ?? null,
                    'access_code' => $result['access_code'] ?? null,
                    'reference' => $result['reference'],
                    'gateway' => $result['gateway'],
                    'amount' => $amount,
                    'currency' => $currency,
                ],
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Subscription plan not found.'], 404);
        } catch (Exception $e) {
            Log::error('One-time payment initialization failed', [
                'user_id' => Auth::id(),
                'plan_uuid' => $request->plan_uuid ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize payment: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Initialize a subscription payment
     *
     * POST /api/v1/payments/subscription/initialize
     */
    public function initializeSubscriptionPayment(Request $request): JsonResponse
    {
        $request->validate([
            'plan_uuid' => 'required|string',
            'currency' => 'nullable|string|in:NGN,USD,GBP,EUR',
            'billing_period' => 'nullable|string|in:monthly,annual',
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
        ]);

        try {
            $user = Auth::user();
            if (! $user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $plan = SubscriptionPlan::where('uuid', $request->plan_uuid)
                ->orWhere('slug', $request->plan_uuid)
                ->where('is_active', true)
                ->firstOrFail();

            // Double charge guard: check if user already has an active subscription for this plan
            $activeSub = PaymentSubscription::where('user_id', $user->id)
                ->where('plan_id', $plan->id)
                ->active()
                ->first();

            if ($activeSub) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active subscription for this plan.',
                    'error' => 'already_subscribed',
                ], 409);
            }

            // Check for existing pending payment for this plan
            $existingPayment = Payment::where('user_id', $user->id)
                ->where('payment_type', 'subscription')
                ->where('status', 'pending')
                ->whereJsonContains('metadata->plan_id', $plan->id)
                ->first();

            if ($existingPayment) {
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription payment already initialized',
                    'data' => [
                        'authorization_url' => $existingPayment->authorization_url,
                        'access_code' => $existingPayment->access_code,
                        'reference' => $existingPayment->reference,
                        'gateway' => $existingPayment->payment_gateway,
                        'amount' => $existingPayment->amount,
                        'currency' => $existingPayment->currency,
                        'reused' => true,
                    ],
                ], 200);
            }

            $currency = $request->input('currency', 'NGN');
            $price = $currency === 'USD' ? ($plan->price_usd ?? $plan->price) : ($plan->price_ngn ?? $plan->price);

            $payment = Payment::create([
                'user_id' => $user->id,
                'amount' => $price,
                'currency' => $currency,
                'payment_type' => 'subscription',
                'description' => 'Subscription: '.$plan->name,
                'metadata' => [
                    'plan_id' => $plan->id,
                    'plan_uuid' => $plan->uuid ?? $request->plan_uuid,
                    'billing_period' => $request->input('billing_period', 'monthly'),
                    'success_url' => $request->success_url,
                    'cancel_url' => $request->cancel_url,
                ],
                'status' => 'draft',
                'payment_status' => 'pending',
            ]);

            $result = $this->paymentProcessor->processPayment($payment, [
                'customer_name' => $user->full_name,
                'customer_email' => $user->email,
                'plan_name' => $plan->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription payment initialized',
                'data' => [
                    'authorization_url' => $result['authorization_url'] ?? null,
                    'access_code' => $result['access_code'] ?? null,
                    'reference' => $result['reference'],
                    'gateway' => $result['gateway'],
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ],
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Subscription plan not found.'], 404);
        } catch (Exception $e) {
            Log::error('Subscription payment initialization failed', [
                'user_id' => Auth::id(),
                'plan_uuid' => $request->plan_uuid,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize subscription payment: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Verify payment by reference
     * GET /api/v1/payments/verify/{reference}
     */
    public function verifyByReference(string $reference): JsonResponse
    {
        try {
            $user = Auth::user();
            $payment = Payment::where('payment_reference', $reference)
                ->where('user_id', $user->id)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Payment verified',
                'data' => [
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'is_paid' => in_array($payment->status, ['completed', 'paid']),
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Payment not found.'], 404);
        }
    }

    /**
     * Initiate a payment (generic)
     * POST /api/v1/payments/initiate
     */
    public function initiatePayment(InitiatePaymentRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $payment = Payment::create([
                'user_id' => $user->id,
                'amount' => $request->amount,
                'currency' => $request->currency ?? 'NGN',
                'payment_type' => $request->payment_type,
                'description' => $request->description ?? null,
                'metadata' => $request->metadata ?? [],
                'status' => 'draft',
                'payment_status' => 'pending',
            ]);

            $processingResult = $this->paymentProcessor->processPayment($payment, [
                'customer_name' => $user->full_name,
                'customer_email' => $user->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'reference' => $processingResult['reference'],
                    'authorization_url' => $processingResult['authorization_url'],
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'gateway' => $processingResult['gateway'],
                    'status' => 'pending',
                ],
            ], 201);
        } catch (Exception $e) {
            Log::error('Payment: Initiate payment failed', ['user_id' => Auth::id(), 'message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to initiate payment: '.$e->getMessage(), 'error' => 'payment_initiation_failed'], 400);
        }
    }

    /** GET /api/v1/payments */
    public function getPaymentHistory(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $perPage = (int) $request->input('per_page', 20);

            $query = Payment::where('user_id', $user->id)->orderByDesc('created_at');
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $payments = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Payment history retrieved',
                'data' => $payments->map(fn ($p) => [
                    'id' => $p->id,
                    'uuid' => $p->uuid ?? (string) $p->id,
                    'user_id' => $p->user_id,
                    'amount' => $p->amount,
                    'currency' => $p->currency,
                    'status' => $p->status,
                    'payment_type' => $p->payment_type,
                    'provider' => $p->payment_gateway,
                    'description' => $p->description,
                    'reference' => $p->payment_reference,
                    'paid_at' => $p->completed_at,
                    'created_at' => $p->created_at,
                    'updated_at' => $p->updated_at,
                ])->values()->all(),
                'meta' => [
                    'total' => $payments->total(),
                    'per_page' => $payments->perPage(),
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to retrieve payment history'], 400);
        }
    }

    /** POST /api/v1/payments/retry/{reference} */
    public function retryPayment(string $reference): JsonResponse
    {
        try {
            $user = Auth::user();
            $payment = Payment::where('payment_reference', $reference)->where('user_id', $user->id)->first();

            if (! $payment) {
                return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
            }
            if ($payment->status === 'completed') {
                return response()->json(['success' => false, 'message' => 'Payment already completed'], 400);
            }

            $result = $this->paymentProcessor->processPayment($payment, [
                'customer_name' => $user->full_name,
                'customer_email' => $user->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment retry initiated',
                'data' => [
                    'payment_id' => $payment->id,
                    'reference' => $result['reference'],
                    'authorization_url' => $result['authorization_url'] ?? null,
                    'access_code' => $result['access_code'] ?? null,
                    'gateway' => $result['gateway'],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Retry failed: '.$e->getMessage()], 400);
        }
    }

    /** Stub payment method endpoints */
    public function getPaymentMethods(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Payment methods retrieved', 'data' => []]);
    }

    public function addPaymentMethod(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Use payment initialization instead.', 'error' => 'not_implemented'], 501);
    }

    public function deletePaymentMethod(string $uuid): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Payment method management requires gateway-level implementation.', 'error' => 'not_implemented'], 501);
    }

    public function setDefaultPaymentMethod(string $uuid): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Payment method management requires gateway-level implementation.', 'error' => 'not_implemented'], 501);
    }

    public function handleWebhook(Request $request, string $gateway): JsonResponse
    {
        Log::info('Payment: Webhook received', ['gateway' => $gateway, 'ip' => $request->ip()]);

        return response()->json(['success' => true, 'message' => 'Webhook acknowledged']);
    }

    public function verifyCallback(Request $request): JsonResponse
    {
        try {
            $reference = $request->get('reference');
            if (! $reference) {
                return response()->json(['success' => false, 'message' => 'Payment reference is required'], 400);
            }
            $payment = Payment::where('payment_reference', $reference)->firstOrFail();
            $this->paymentProcessor->verifyPayment($payment);

            return response()->json([
                'success' => true,
                'message' => 'Payment verification completed',
                'data' => ['payment_id' => $payment->id, 'status' => $payment->status, 'is_paid' => $payment->status === 'completed'],
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Callback verification failed'], 400);
        }
    }

    public function getPaymentSummary(): JsonResponse
    {
        $user = Auth::user();
        $payments = $user->payments();

        return response()->json([
            'success' => true,
            'message' => 'Payment summary retrieved',
            'data' => [
                'total_payments' => $payments->count(),
                'total_amount_paid' => $payments->where('status', 'completed')->sum('amount'),
                'pending_amount' => $payments->where('status', 'pending')->sum('amount'),
                'failed_payments' => $payments->where('status', 'failed')->count(),
                'currency' => 'NGN',
            ],
        ]);
    }

    public function getPayment(Payment $payment): JsonResponse
    {
        $user = Auth::user();
        if ($payment->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment details retrieved',
            'data' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'payment_type' => $payment->payment_type,
                'description' => $payment->description,
                'gateway' => $payment->payment_gateway,
                'reference' => $payment->payment_reference,
                'metadata' => $payment->metadata,
                'created_at' => $payment->created_at,
                'completed_at' => $payment->completed_at,
            ],
        ]);
    }

    public function refundPayment(Payment $payment, RefundPaymentRequest $request): JsonResponse
    {
        $user = Auth::user();
        if ($payment->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $this->paymentProcessor->refundPayment($payment, $request->amount);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => ['payment_id' => $payment->id, 'refund_amount' => $request->amount, 'status' => $payment->status],
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Refund processing failed: '.$e->getMessage()], 400);
        }
    }

    public function verifyPayment(Payment $payment, VerifyPaymentRequest $request): JsonResponse
    {
        $user = Auth::user();
        if ($payment->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $this->paymentProcessor->verifyPayment($payment);

        return response()->json([
            'success' => true,
            'message' => 'Payment verification completed',
            'data' => ['payment_id' => $payment->id, 'status' => $payment->status, 'is_paid' => $payment->status === 'completed'],
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Derive the platform booking fee for a user based on their ACTIVE subscription plan.
     *
     * Fee-exempt plan slugs: premium, recovery.
     * All other plans (basic, freemium / no subscription) pay the config-driven fee.
     *
     * Reads config from Setting model first, then PLATFORM_BOOKING_FEE_NGN env, then 3000.
     */
    private function derivePlatformFee(object $user): float
    {
        $exemptPlanSlugs = ['premium', 'recovery'];
        $activePlanSlug = null;

        try {
            $paySub = PaymentSubscription::where('user_id', $user->id)
                ->active()
                ->with('plan')
                ->latest()
                ->first();
            if ($paySub && $paySub->plan) {
                $activePlanSlug = $paySub->plan->slug;
            }
        } catch (\Throwable) {
        }

        if (! $activePlanSlug) {
            try {
                $legacySub = LegacySubscription::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('current_period_end', '>=', now())
                    ->with('plan')
                    ->latest('current_period_end')
                    ->first();
                if ($legacySub && $legacySub->plan) {
                    $activePlanSlug = $legacySub->plan->slug;
                }
            } catch (\Throwable) {
            }
        }

        // Fast path: user's cached subscription_plan field
        if (! $activePlanSlug && ! empty($user->subscription_plan)) {
            $activePlanSlug = $user->subscription_plan;
        }

        if ($activePlanSlug && in_array($activePlanSlug, $exemptPlanSlugs)) {
            return 0.0;
        }

        return (float) (
            Setting::where('key', 'platform_booking_fee_ngn')->value('value')
            ?? env('PLATFORM_BOOKING_FEE_NGN', 3000)
        );
    }
}
