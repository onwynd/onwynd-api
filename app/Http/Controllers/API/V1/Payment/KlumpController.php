<?php

namespace App\Http\Controllers\API\V1\Payment;

use App\Helpers\GatewaySettings;
use App\Http\Controllers\API\BaseController;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Klump BNPL gateway â€” Buy Now Pay Later for Nigerian users.
 * Klump uses a JavaScript widget on the frontend. The backend returns the
 * public key and payment config; the widget is opened client-side.
 */
class KlumpController extends BaseController
{
    private string $publicKey;
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $gw = GatewaySettings::gateway('klump') ?? [];
        $this->publicKey = (string) (($gw['public_key'] ?? null) ?: config('services.klump.public_key', ''));
        $this->secretKey = (string) (($gw['secret_key'] ?? null) ?: config('services.klump.secret_key', ''));
        $this->baseUrl = (string) config('services.klump.base_url', 'https://api.useklump.com/v1');
    }

    /**
     * POST /api/v1/payments/klump/initialize
     * Returns the Klump widget config for the frontend to open the BNPL popup.
     */
    public function initialize(Request $request): JsonResponse
    {
        $request->validate([
            'plan_uuid'      => 'required|string',
            'billing_period' => 'required|string|in:monthly,quarterly,yearly,one_time',
        ]);

        $user = Auth::user();
        if (! $user) {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        // Sandbox mode
        if (empty($this->publicKey)) {
            return $this->sendResponse([
                'public_key'          => 'pk_sandbox_'.Str::random(20),
                'merchant_reference'  => 'KLUMP-SANDBOX-'.Str::upper(Str::random(10)),
                'amount'              => 100000,
                'currency'            => 'NGN',
                'items'               => [[
                    'image_url'  => config('app.url').'/logo.png',
                    'item_url'   => config('app.url').'/subscription',
                    'name'       => 'Subscription Plan',
                    'unit_price' => '100000',
                    'quantity'   => 1,
                ]],
                'sandbox'             => true,
            ], 'Klump sandbox config returned.');
        }

        $plan = SubscriptionPlan::where('uuid', $request->input('plan_uuid'))
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return $this->sendError('Subscription plan not found.', [], 404);
        }

        $amount = (int) (($plan->price_ngn ?? $plan->price) * 100); // kobo
        $ref    = 'KLUMP-'.Str::upper(Str::random(10)).'-'.$user->id;

        return $this->sendResponse([
            'public_key'         => $this->publicKey,
            'merchant_reference' => $ref,
            'amount'             => $plan->price_ngn ?? $plan->price,
            'currency'           => 'NGN',
            'items'              => [[
                'image_url'  => config('app.url').'/logo.png',
                'item_url'   => config('app.url').'/subscription',
                'name'       => $plan->name.' Subscription',
                'unit_price' => (string) ($plan->price_ngn ?? $plan->price),
                'quantity'   => 1,
            ]],
        ], 'Klump payment config returned.');
    }
}
