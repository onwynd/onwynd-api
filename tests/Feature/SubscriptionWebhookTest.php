<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that webhook endpoints reject requests with invalid/missing signatures.
 *
 * Webhook routes (from routes/api.php):
 *   POST /api/v1/payment/webhook/paystack
 *   POST /api/v1/payment/webhook/stripe
 */
class SubscriptionWebhookTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_paystack_webhook_with_invalid_signature_rejected(): void
    {
        $response = $this->postJson('/api/v1/payment/webhook/paystack', [
            'event' => 'subscription.create',
            'data'  => ['subscription_code' => 'SUB_xxx'],
        ], [
            'X-Paystack-Signature' => 'invalid-signature',
        ]);

        // Paystack controller must return 400/401/403 when signature check fails
        $this->assertContains($response->status(), [400, 401, 403]);
    }

    /** @test */
    public function test_stripe_webhook_with_invalid_signature_rejected(): void
    {
        $response = $this->postJson('/api/v1/payment/webhook/stripe', [
            'type' => 'customer.subscription.created',
        ], [
            'Stripe-Signature' => 'invalid-signature',
        ]);

        // Stripe controller must return 400/401/403 when signature check fails
        $this->assertContains($response->status(), [400, 401, 403]);
    }
}
