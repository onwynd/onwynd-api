<?php

namespace App\Http\Controllers\API\V1\Therapist;

use App\Helpers\GatewaySettings;
use App\Http\Controllers\API\BaseController;
use App\Models\Therapist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Stripe;

/**
 * Stripe Connect OAuth flow for international (USD) therapists.
 *
 * Flow:
 *   1. GET  /therapist/stripe/connect          → returns OAuth URL
 *   2. Stripe redirects → GET /therapist/stripe/callback?code=…
 *   3. GET  /therapist/stripe/status           → returns connection state
 *   4. POST /therapist/stripe/disconnect       → unlinks account
 */
class StripeConnectController extends BaseController
{
    public function __construct()
    {
        Stripe::setApiKey(GatewaySettings::secretKey('stripe', config('services.stripe.secret', '')));
    }

    /**
     * Step 1 — Generate Stripe Connect OAuth URL.
     */
    public function connect(Request $request): JsonResponse
    {
        $therapist = $this->getTherapistProfile($request);
        if (! $therapist) {
            return $this->sendError('Therapist profile not found.', [], 404);
        }

        if ($therapist->stripe_connected) {
            return $this->sendError('Stripe account already connected.', [], 409);
        }

        try {
            // Create an Express account for the therapist
            $account = Account::create([
                'type'    => 'express',
                'country' => $therapist->country_of_operation ?? 'US',
                'email'   => $request->user()->email,
                'capabilities' => [
                    'transfers' => ['requested' => true],
                ],
                'metadata' => [
                    'therapist_user_id' => (string) $request->user()->id,
                ],
            ]);

            $therapist->stripe_connect_account_id = $account->id;
            $therapist->save();

            $returnUrl  = config('app.frontend_url') . '/therapist/dashboard/settings?stripe=success';
            $refreshUrl = config('app.frontend_url') . '/therapist/dashboard/settings?stripe=refresh';

            $link = AccountLink::create([
                'account'     => $account->id,
                'return_url'  => $returnUrl,
                'refresh_url' => $refreshUrl,
                'type'        => 'account_onboarding',
            ]);

            return $this->sendResponse([
                'url'        => $link->url,
                'account_id' => $account->id,
            ], 'Stripe Connect URL generated.');

        } catch (\Throwable $e) {
            Log::error('StripeConnectController: connect failed', [
                'user_id' => $request->user()->id,
                'error'   => $e->getMessage(),
            ]);
            return $this->sendError('Failed to initiate Stripe Connect: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Step 2 — Stripe redirects back after account creation/onboarding.
     * Marks the account as connected if Stripe confirms it.
     */
    public function callback(Request $request): JsonResponse
    {
        $therapist = $this->getTherapistProfile($request);
        if (! $therapist || ! $therapist->stripe_connect_account_id) {
            return $this->sendError('No pending Stripe Connect session.', [], 400);
        }

        try {
            $account = Account::retrieve($therapist->stripe_connect_account_id);
            $connected = $account->charges_enabled && $account->payouts_enabled;

            $therapist->stripe_connected = $connected;
            $therapist->save();

            return $this->sendResponse([
                'connected'    => $connected,
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
            ], $connected ? 'Stripe account connected successfully.' : 'Stripe onboarding incomplete — please complete your Stripe profile.');

        } catch (\Throwable $e) {
            Log::error('StripeConnectController: callback failed', [
                'user_id'    => $request->user()->id,
                'account_id' => $therapist->stripe_connect_account_id,
                'error'      => $e->getMessage(),
            ]);
            return $this->sendError('Failed to verify Stripe account: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Return current Stripe Connect status for the therapist dashboard.
     */
    public function status(Request $request): JsonResponse
    {
        $therapist = $this->getTherapistProfile($request);
        if (! $therapist) {
            return $this->sendError('Therapist profile not found.', [], 404);
        }

        if (! $therapist->stripe_connect_account_id) {
            return $this->sendResponse([
                'connected'  => false,
                'account_id' => null,
            ], 'Stripe not connected.');
        }

        try {
            $account = Account::retrieve($therapist->stripe_connect_account_id);
            return $this->sendResponse([
                'connected'       => $therapist->stripe_connected,
                'account_id'      => $therapist->stripe_connect_account_id,
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
                'requirements'    => $account->requirements?->currently_due ?? [],
            ], 'Stripe Connect status retrieved.');

        } catch (\Throwable $e) {
            return $this->sendResponse([
                'connected'  => false,
                'account_id' => $therapist->stripe_connect_account_id,
                'error'      => 'Could not fetch live status from Stripe.',
            ], 'Stripe status check failed.');
        }
    }

    /**
     * Unlink Stripe Connect account.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $therapist = $this->getTherapistProfile($request);
        if (! $therapist) {
            return $this->sendError('Therapist profile not found.', [], 404);
        }

        $therapist->stripe_connect_account_id = null;
        $therapist->stripe_connected = false;
        $therapist->save();

        return $this->sendResponse([], 'Stripe account disconnected.');
    }

    private function getTherapistProfile(Request $request): ?Therapist
    {
        return Therapist::where('user_id', $request->user()->id)->first();
    }
}
