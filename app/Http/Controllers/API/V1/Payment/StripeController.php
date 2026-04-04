<?php

namespace App\Http\Controllers\API\V1\Payment;

use App\Helpers\GatewaySettings;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;

class StripeController extends Controller
{
    public function checkout(Request $request)
    {
        $request->validate([
            'plan_uuid' => 'required_without:price_id|string',
            'price_id' => 'required_without:plan_uuid|string',
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
            'email' => 'nullable|email',
        ]);

        Stripe::setApiKey(GatewaySettings::secretKey('stripe', config('services.stripe.secret', '')));

        $user = $request->user();
        $email = $user?->email ?? $request->input('email');

        $successUrl = $request->input('success_url', config('app.url').'/subscription/success?session_id={CHECKOUT_SESSION_ID}');
        $cancelUrl = $request->input('cancel_url', config('app.url').'/subscription');

        // Build line_items — either from a Stripe price_id or an inline price from the plan
        if ($request->filled('price_id')) {
            $lineItems = [[
                'price' => $request->string('price_id'),
                'quantity' => 1,
            ]];
        } else {
            $plan = SubscriptionPlan::where('uuid', $request->plan_uuid)
                ->orWhere('slug', $request->plan_uuid)
                ->where('is_active', true)
                ->firstOrFail();

            $lineItems = [[
                'price_data' => [
                    'currency' => strtolower($plan->currency ?? 'usd'),
                    'product_data' => ['name' => $plan->name.' Subscription'],
                    'unit_amount' => (int) ($plan->price * 100),
                    'recurring' => ['interval' => $plan->billing_interval === 'yearly' ? 'year' : 'month'],
                ],
                'quantity' => 1,
            ]];
        }

        $session = StripeCheckoutSession::create([
            'mode' => 'subscription',
            'line_items' => $lineItems,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) ($user?->id ?? ''),
            'customer_email' => $email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Stripe checkout session created',
            'data' => [
                'session_id' => $session->id,
                'session_url' => $session->url,
            ],
        ], 201);
    }

    public function verifySession(Request $request)
    {
        $request->validate(['session_id' => 'required|string']);

        try {
            Stripe::setApiKey(GatewaySettings::secretKey('stripe', config('services.stripe.secret', '')));
            $session = StripeCheckoutSession::retrieve($request->string('session_id'));

            return response()->json([
                'success' => true,
                'message' => 'Session retrieved',
                'data' => [
                    'status' => $session->status, // 'complete', 'open', 'expired'
                    'payment_status' => $session->payment_status,
                    'customer_email' => $session->customer_details?->email ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve session: '.$e->getMessage(),
            ], 400);
        }
    }
}
