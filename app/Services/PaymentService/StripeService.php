<?php

namespace App\Services\PaymentService;

use App\Helpers\GatewaySettings;
use App\Models\Payment;
use App\Models\Payment\Subscription as PaymentSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\Charge;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Customer;
use Stripe\Event as StripeEvent;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(GatewaySettings::secretKey('stripe', config('services.stripe.secret', '')));
    }

    /**
     * Initialize a one-time payment via Stripe Checkout Session.
     * Returns authorization_url (the hosted checkout page URL) for redirect.
     */
    public function initializePayment(float $amount, string $currency, string $email, string $reference, array $metadata = []): array
    {
        try {
            $session = StripeCheckoutSession::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency'     => strtolower($currency),
                        'product_data' => ['name' => $metadata['title'] ?? config('app.name').' Session'],
                        'unit_amount'  => (int) round($amount * 100), // Stripe expects smallest currency unit
                    ],
                    'quantity' => 1,
                ]],
                'mode'              => 'payment',
                'customer_email'    => $email,
                'client_reference_id' => $reference,
                'success_url'       => $metadata['success_url'] ?? config('app.url'),
                'cancel_url'        => $metadata['cancel_url'] ?? config('app.url'),
                'metadata'          => ['reference' => $reference],
            ]);

            return [
                'success'          => true,
                'authorization_url' => $session->url,
                'gateway_payment_id' => $session->id,
                'reference'        => $reference,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe initializePayment Error: '.$e->getMessage());

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create a Payment Intent
     *
     * @param  float  $amount  Amount in cents
     * @param  string  $currency  Currency code (e.g., 'usd')
     * @param  array  $metadata  Additional metadata
     * @return \Stripe\PaymentIntent|array
     */
    public function createPaymentIntent($amount, $currency = 'usd', $metadata = [])
    {
        try {
            return PaymentIntent::create([
                'amount' => $amount,
                'currency' => $currency,
                'metadata' => $metadata,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe PaymentIntent Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create a Customer
     *
     * @param  string  $email
     * @param  string  $name
     * @return \Stripe\Customer|array
     */
    public function createCustomer($email, $name)
    {
        try {
            return Customer::create([
                'email' => $email,
                'name' => $name,
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe Customer Creation Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create a Charge (Legacy)
     *
     * @param  float  $amount
     * @param  string  $currency
     * @param  string  $source
     * @return \Stripe\Charge|array
     */
    public function createCharge($amount, $currency, $source)
    {
        try {
            return Charge::create([
                'amount' => $amount,
                'currency' => $currency,
                'source' => $source,
                'description' => 'Charge for '.config('app.name'),
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe Charge Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Verify a Checkout Session by its payment reference or Stripe session ID.
     * Looks up the Payment record to get the gateway_payment_id (session ID).
     */
    public function verifyPayment(string $reference): array
    {
        try {
            // The reference may be a Stripe session ID or our internal payment reference.
            // Try session ID first, fall back to looking up via our payment record.
            $sessionId = $reference;

            if (! str_starts_with($reference, 'cs_')) {
                // Internal reference — look up the Stripe session ID from the Payment record
                $payment = \App\Models\Payment::where('payment_reference', $reference)->first();
                if ($payment && $payment->gateway_payment_id) {
                    $sessionId = $payment->gateway_payment_id;
                }
            }

            $session = StripeCheckoutSession::retrieve($sessionId);
            $paid = $session->payment_status === 'paid';

            return [
                'success'        => $paid,
                'status'         => $paid ? 'success' : $session->payment_status,
                'transaction_id' => $session->payment_intent ?? $session->id,
                'reference'      => $reference,
                'amount'         => $session->amount_total ? $session->amount_total / 100 : null,
                'currency'       => strtoupper($session->currency ?? ''),
                'paid_at'        => $paid ? now()->toIso8601String() : null,
                'gateway'        => 'stripe',
            ];
        } catch (\Exception $e) {
            Log::error('Stripe verifyPayment Error: '.$e->getMessage());

            return ['success' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Handle Stripe webhook events (after signature verification)
     */
    public function handleWebhookEvent(StripeEvent $event): void
    {
        try {
            $type = $event->type;
            $payload = $event->toArray();

            Log::info('Stripe: Handling webhook', ['type' => $type]);

            if ($type === 'invoice.payment_failed') {
                $email = $payload['data']['object']['customer_email'] ?? null;
                $reference = $payload['data']['object']['id'] ?? null;

                $user = null;
                if ($email) {
                    $user = User::where('email', $email)->first();
                }
                if (! $user && $reference) {
                    $payment = Payment::where('gateway_payment_id', $reference)
                        ->orWhere('payment_reference', $reference)
                        ->first();
                    if ($payment) {
                        $user = $payment->user;
                    }
                }
                if ($user) {
                    $user->subscription_status = 'free';
                    $user->subscription_ends_at = now();
                    $user->save();

                    PaymentSubscription::where('user_id', $user->id)
                        ->where('status', 'active')
                        ->update([
                            'status' => 'expired',
                            'expires_at' => now(),
                            'canceled_at' => now(),
                        ]);

                    Log::info('Stripe: User downgraded on invoice.payment_failed', ['user_id' => $user->id]);
                } else {
                    Log::warning('Stripe: Could not resolve user for downgrade on payment_failed');
                }
            }
        } catch (\Throwable $e) {
            Log::error('Stripe: Webhook handling error', ['message' => $e->getMessage()]);
        }
    }
}
