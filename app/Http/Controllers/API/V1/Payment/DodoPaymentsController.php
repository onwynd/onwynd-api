<?php

namespace App\Http\Controllers\API\V1\Payment;

use App\Helpers\GatewaySettings;
use App\Http\Controllers\API\BaseController;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DodoPayments gateway for international (USD) users.
 * Used as the primary foreign-currency gateway while Stripe onboarding is pending.
 *
 * Docs: https://dodopayments.com/docs
 * Keys: DODO_SECRET_KEY, DODO_PUBLIC_KEY in .env
 */
class DodoPaymentsController extends BaseController
{
    private string $baseUrl;
    private string $secretKey;

    public function __construct()
    {
        $this->baseUrl   = 'https://api.dodopayments.com/v1';
        $this->secretKey = GatewaySettings::secretKey('dodopayments', config('services.dodopayments.secret_key', ''));
    }

    /**
     * Initialize a DodoPayments checkout session.
     * POST /api/v1/payments/dodo/initialize
     */
    public function initialize(Request $request): JsonResponse
    {
        $request->validate([
            'plan_uuid'      => 'required|string',
            'billing_period' => 'required|string|in:monthly,quarterly,yearly,one_time',
            'currency'       => 'required|string|in:USD',
            'success_url'    => 'nullable|url',
            'cancel_url'     => 'nullable|url',
        ]);

        $user = Auth::user();
        if (! $user) {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        $planUuid = $request->input('plan_uuid');
        $billingPeriod = $request->input('billing_period');
        $successUrlInput = $request->input('success_url');
        $cancelUrlInput  = $request->input('cancel_url');

        $plan = SubscriptionPlan::where('uuid', $planUuid)
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return $this->sendError('Plan not found or inactive.', [], 404);
        }

        // Amount in USD cents
        $amountUsd = (float) ($plan->getAttribute('price_usd') ?? 0);
        if ($amountUsd <= 0) {
            return $this->sendError('This plan is not available in USD.', [], 422);
        }

        $reference = 'DODO-' . strtoupper(Str::random(12));
        $siteUrl   = config('app.url', 'https://onwynd.com');

        $successUrl = $successUrlInput ?: "{$siteUrl}/subscription/success?ref={$reference}&gateway=dodo";
        $cancelUrl  = $cancelUrlInput  ?: "{$siteUrl}/subscription?cancelled=1";

        // If DodoPayments API key is configured, call real API; otherwise return a
        // sandbox/placeholder response so the rest of the flow can be tested.
        if (empty($this->secretKey) || str_starts_with($this->secretKey, 'sk_test_placeholder')) {
            return $this->sendResponse([
                'checkout_url' => $successUrl . '&sandbox=1',
                'reference'    => $reference,
                'gateway'      => 'dodopayments',
                'amount'       => $amountUsd,
                'currency'     => 'USD',
                'sandbox'      => true,
            ], 'DodoPayments sandbox checkout initialized.');
        }

        try {
            $payload = [
                'amount'      => (int) round($amountUsd * 100), // cents
                'currency'    => 'USD',
                'reference'   => $reference,
                'customer'    => [
                    'email' => $user->getAttribute('email'),
                    'name'  => $user->getAttribute('name') ?? $user->getAttribute('email'),
                ],
                'metadata'    => [
                    'user_id'        => $user->getAttribute('id'),
                    'plan_uuid'      => $plan->getAttribute('uuid'),
                    'billing_period' => $billingPeriod,
                ],
                'redirect_url' => $successUrl,
                'cancel_url'   => $cancelUrl,
            ];

            $response = Http::withToken($this->secretKey)
                ->post("{$this->baseUrl}/checkout/sessions", $payload);

            if (! $response->successful()) {
                Log::error('DodoPayments init failed', [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'user_id' => $user->id,
                ]);
                return $this->sendError('Payment gateway error. Please try again.', [], 502);
            }

            $data = $response->json();

            return $this->sendResponse([
                'checkout_url' => $data['checkout_url'] ?? $data['url'] ?? $successUrl,
                'reference'    => (string) ($data['id'] ?? $reference),
                'gateway'      => 'dodopayments',
                'amount'       => $amountUsd,
                'currency'     => 'USD',
            ], 'Checkout session created.');
        } catch (\Throwable $e) {
            Log::error('DodoPayments exception', ['error' => $e->getMessage()]);
            return $this->sendError('Payment initialization failed.', [], 500);
        }
    }

    /**
     * Verify a completed DodoPayments transaction.
     * GET /api/v1/payments/dodo/verify/{reference}
     */
    public function verify(string $reference): JsonResponse
    {
        if (empty($this->secretKey) || str_starts_with($this->secretKey, 'sk_test_placeholder')) {
            return $this->sendResponse([
                'success' => true,
                'status'  => 'completed',
                'sandbox' => true,
            ], 'Sandbox verification passed.');
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->get("{$this->baseUrl}/payments/{$reference}");

            if (! $response->successful()) {
                return $this->sendResponse(['success' => false, 'status' => 'failed'], 'Verification failed.');
            }

            $data   = $response->json();
            $status = strtolower((string) ($data['status'] ?? 'unknown'));

            return $this->sendResponse([
                'success' => \in_array($status, ['completed', 'paid', 'succeeded'], true),
                'status'  => $status,
                'data'    => $data,
            ], 'Payment verified.');
        } catch (\Throwable $e) {
            Log::error('DodoPayments verify exception', ['error' => $e->getMessage()]);
            return $this->sendError('Verification error.', [], 500);
        }
    }

    /**
     * Handle DodoPayments webhook.
     * POST /api/v1/payment/webhook/dodo
     */
    public function webhook(Request $request): JsonResponse
    {
        // Verify webhook signature using HMAC-SHA256 (DodoPayments svix-based signing)
        $webhookSecret = config('services.dodopayments.webhook_secret');
        if ($webhookSecret) {
            $svixId        = $request->header('svix-id', '');
            $svixTimestamp = $request->header('svix-timestamp', '');
            $svixSignature = $request->header('svix-signature', '');
            $rawBody       = $request->getContent();

            // Reject requests older than 5 minutes to prevent replay attacks
            if (abs(time() - (int) $svixTimestamp) > 300) {
                Log::warning('DodoPayments webhook rejected: timestamp too old', ['ts' => $svixTimestamp]);
                return response()->json(['error' => 'Request too old.'], 403);
            }

            $signedContent = "{$svixId}.{$svixTimestamp}.{$rawBody}";
            $secretBytes   = base64_decode(preg_replace('/^whsec_/', '', $webhookSecret));
            $computedHmac  = base64_encode(hash_hmac('sha256', $signedContent, $secretBytes, true));

            // svix-signature may contain multiple space-separated "v1,<hash>" values
            $valid = false;
            foreach (explode(' ', $svixSignature) as $sig) {
                if (str_starts_with($sig, 'v1,') && hash_equals(substr($sig, 3), $computedHmac)) {
                    $valid = true;
                    break;
                }
            }

            if (! $valid) {
                Log::warning('DodoPayments webhook rejected: invalid signature');
                return response()->json(['error' => 'Invalid signature.'], 403);
            }
        }

        $event = $request->input('event');
        $data  = $request->input('data', []);

        Log::info('DodoPayments webhook received', ['event' => $event]);

        // Handle payment.completed — activate subscription
        if ($event === 'payment.completed' || $event === 'checkout.completed') {
            $reference = $data['id'] ?? $data['reference'] ?? null;
            $metadata  = $data['metadata'] ?? [];
            $userId    = $metadata['user_id'] ?? null;
            $planUuid  = $metadata['plan_uuid'] ?? null;

            if ($reference && $userId && $planUuid) {
                // Delegate subscription activation to the existing payment service
                try {
                    $plan = SubscriptionPlan::where('uuid', $planUuid)->first();
                    if ($plan) {
                        // Fire same event as Paystack/Stripe success for consistent handling
                        \Illuminate\Support\Facades\Event::dispatch(
                            'payment.confirmed',
                            ['user_id' => $userId, 'plan' => $plan, 'reference' => $reference, 'gateway' => 'dodopayments']
                        );
                    }
                } catch (\Throwable $e) {
                    Log::error('DodoPayments webhook activation error', ['error' => $e->getMessage()]);
                }
            }
        }

        return response()->json(['received' => true]);
    }
}
