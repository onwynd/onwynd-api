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
 * Flutterwave gateway — NGN standard payments (alternative to Paystack).
 * Uses Flutterwave's Standard payment flow: backend creates a payment link,
 * frontend redirects user to it, callback/webhook confirms completion.
 */
class FlutterwaveController extends BaseController
{
    private string $secretKey;
    private string $publicKey;
    private string $baseUrl = 'https://api.flutterwave.com/v3';
    private bool $isTest;

    public function __construct()
    {
        $gw = GatewaySettings::gateway('flutterwave');
        $this->secretKey = $gw['secret_key'] ?: config('services.flutterwave.secret_key', '');
        $this->publicKey = $gw['public_key'] ?: config('services.flutterwave.public_key', '');
        $this->isTest    = $gw['mode'] !== 'live';
    }

    /**
     * POST /api/v1/payments/flutterwave/initialize
     */
    public function initialize(Request $request): JsonResponse
    {
        $request->validate([
            'plan_uuid'      => 'required|string',
            'billing_period' => 'required|string|in:monthly,quarterly,yearly,one_time',
            'currency'       => 'nullable|string|in:NGN',
            'amount'         => 'nullable|numeric|min:100',
            'success_url'    => 'nullable|url',
            'cancel_url'     => 'nullable|url',
        ]);

        $user = Auth::user();
        if (! $user) {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        // Sandbox mode when no key is configured
        if (empty($this->secretKey)) {
            $ref = 'FW-SANDBOX-'.Str::upper(Str::random(12));
            return $this->sendResponse([
                'payment_link'  => $request->input('success_url', config('app.url').'/subscription/verify?gateway=flutterwave&reference='.$ref),
                'tx_ref'        => $ref,
                'gateway'       => 'flutterwave',
                'sandbox'       => true,
            ], 'Flutterwave sandbox payment link created.');
        }

        $plan = SubscriptionPlan::where('uuid', $request->input('plan_uuid'))
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return $this->sendError('Subscription plan not found.', [], 404);
        }

        $currency = $request->input('currency', 'NGN');
        $amount   = (float) ($request->input('amount') ?? ($currency === 'NGN' ? ($plan->price_ngn ?? $plan->price) : $plan->price));
        $txRef    = 'FW-'.Str::upper(Str::random(10)).'-'.$user->id;

        $successUrl = $request->input('success_url', config('app.url').'/subscription/verify?gateway=flutterwave');
        $cancelUrl  = $request->input('cancel_url', config('app.url').'/subscription');

        try {
            $response = Http::withToken($this->secretKey)
                ->post("{$this->baseUrl}/payments", [
                    'tx_ref'          => $txRef,
                    'amount'          => $amount,
                    'currency'        => $currency,
                    'redirect_url'    => $successUrl,
                    'customer'        => [
                        'email'       => $user->email,
                        'name'        => $user->full_name ?? $user->name ?? $user->email,
                        'phonenumber' => $user->phone ?? '',
                    ],
                    'customizations'  => [
                        'title'       => config('app.name', 'Onwynd').' Subscription',
                        'description' => $plan->name.' — '.$request->input('billing_period'),
                        'logo'        => config('app.url').'/logo.png',
                    ],
                    'meta' => [
                        'plan_uuid'      => $plan->uuid,
                        'billing_period' => $request->input('billing_period'),
                        'user_id'        => $user->id,
                    ],
                ]);

            $data = $response->json();

            if (($data['status'] ?? '') === 'success' && isset($data['data']['link'])) {
                return $this->sendResponse([
                    'payment_link' => $data['data']['link'],
                    'tx_ref'       => $txRef,
                    'gateway'      => 'flutterwave',
                ], 'Payment link created.');
            }

            Log::error('Flutterwave initialize error', ['response' => $data]);

            return $this->sendError($data['message'] ?? 'Failed to create payment link.', [], 400);
        } catch (\Exception $e) {
            Log::error('Flutterwave initialize exception: '.$e->getMessage());

            return $this->sendError('Payment initialization failed.', [], 500);
        }
    }

    /**
     * GET /api/v1/payments/flutterwave/verify/{tx_ref}
     */
    public function verify(string $txRef): JsonResponse
    {
        if (empty($this->secretKey)) {
            return $this->sendResponse(['status' => 'success', 'sandbox' => true], 'Sandbox verification passed.');
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->get("{$this->baseUrl}/transactions", ['tx_ref' => $txRef]);

            $data = $response->json();

            if (($data['status'] ?? '') === 'success' && ! empty($data['data'])) {
                $tx = $data['data'][0];

                return $this->sendResponse([
                    'status'    => $tx['status'],
                    'amount'    => $tx['amount'],
                    'currency'  => $tx['currency'],
                    'tx_ref'    => $tx['tx_ref'],
                    'gateway'   => 'flutterwave',
                ], 'Payment verified.');
            }

            return $this->sendError('Transaction not found.', [], 404);
        } catch (\Exception $e) {
            Log::error('Flutterwave verify exception: '.$e->getMessage());

            return $this->sendError('Verification failed.', [], 500);
        }
    }
}
