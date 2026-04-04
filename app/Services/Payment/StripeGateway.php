<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Services\PaymentService\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeGateway implements PaymentGatewayInterface
{
    public function __construct(private StripeService $stripe) {}

    /**
     * Initiate a payment via Stripe Checkout Session.
     *
     * Expected keys in $payload: amount, currency, email, reference, metadata (optional).
     */
    public function initiate(array $payload): array
    {
        try {
            return $this->stripe->initializePayment(
                (float) ($payload['amount'] ?? 0),
                (string) ($payload['currency'] ?? 'USD'),
                (string) ($payload['email'] ?? ''),
                (string) ($payload['reference'] ?? ''),
                (array) ($payload['metadata'] ?? [])
            );
        } catch (\Throwable $e) {
            Log::error('StripeGateway::initiate failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify a completed payment by reference or Stripe session ID.
     */
    public function verify(string $reference): array
    {
        try {
            return $this->stripe->verifyPayment($reference);
        } catch (\Throwable $e) {
            Log::error('StripeGateway::verify failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Webhook handling is delegated to the dedicated StripeController.
     * This method is a no-op at the gateway-abstraction level.
     */
    public function handleWebhook(Request $request): void
    {
        // Intentionally empty: webhook routing is handled by StripeController,
        // which verifies the Stripe-Signature header and calls StripeService::handleWebhookEvent.
    }

    /**
     * Stripe Connect payouts use a separate API flow (Stripe Connect / Transfer objects).
     * This is not yet implemented; throw a clear exception so callers know immediately.
     *
     * @throws \RuntimeException always.
     */
    public function createTransferRecipient(array $bankDetails): string
    {
        throw new \RuntimeException(
            'StripeGateway::createTransferRecipient is not yet implemented. '
            . 'Use Stripe Connect to manage therapist payouts.'
        );
    }

    /**
     * Stripe Connect payouts use a separate API flow.
     * Not yet implemented; throws a clear exception.
     *
     * @throws \RuntimeException always.
     */
    public function transfer(array $payload): array
    {
        throw new \RuntimeException(
            'StripeGateway::transfer is not yet implemented. '
            . 'Use Stripe Connect to manage therapist payouts.'
        );
    }

    public function supports(string $currency): bool
    {
        return $currency === 'USD';
    }

    public function getName(): string
    {
        return 'Stripe';
    }
}
