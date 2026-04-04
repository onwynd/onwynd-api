<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Services\PaymentService\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackGateway implements PaymentGatewayInterface
{
    public function __construct(private PaystackService $paystack) {}

    /**
     * Initiate a payment transaction.
     *
     * Expected keys in $payload: amount, email, reference, metadata (optional).
     */
    public function initiate(array $payload): array
    {
        try {
            return $this->paystack->initializePayment(
                (float) ($payload['amount'] ?? 0),
                (string) ($payload['email'] ?? ''),
                (string) ($payload['reference'] ?? ''),
                (array) ($payload['metadata'] ?? [])
            );
        } catch (\Throwable $e) {
            Log::error('PaystackGateway::initiate failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify a completed payment by reference.
     */
    public function verify(string $reference): array
    {
        try {
            return $this->paystack->verifyPayment($reference);
        } catch (\Throwable $e) {
            Log::error('PaystackGateway::verify failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Webhook handling is delegated to the dedicated PaystackController.
     * This method is a no-op at the gateway-abstraction level.
     */
    public function handleWebhook(Request $request): void
    {
        // Intentionally empty: webhook routing is handled by PaystackController,
        // which calls PaystackService::verifyWebhookSignature and handleWebhookEvent directly.
    }

    /**
     * Create a Paystack transfer recipient.
     *
     * Expected keys in $bankDetails: account_number, bank_code, account_name, currency (optional).
     *
     * @throws \RuntimeException when the underlying service call fails.
     */
    public function createTransferRecipient(array $bankDetails): string
    {
        $result = $this->paystack->createTransferRecipient(
            (string) ($bankDetails['account_number'] ?? ''),
            (string) ($bankDetails['bank_code'] ?? ''),
            (string) ($bankDetails['account_name'] ?? ''),
            (string) ($bankDetails['currency'] ?? 'NGN')
        );

        if (! ($result['success'] ?? false)) {
            throw new \RuntimeException($result['message'] ?? 'Failed to create transfer recipient');
        }

        return (string) ($result['recipient_code'] ?? '');
    }

    /**
     * Initiate a payout transfer.
     *
     * Expected keys in $payload: amount (kobo), recipient_code, reference, reason.
     */
    public function transfer(array $payload): array
    {
        try {
            return $this->paystack->initiateTransfer(
                (int) ($payload['amount'] ?? 0),
                (string) ($payload['recipient_code'] ?? ''),
                (string) ($payload['reference'] ?? ''),
                (string) ($payload['reason'] ?? '')
            );
        } catch (\Throwable $e) {
            Log::error('PaystackGateway::transfer failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function supports(string $currency): bool
    {
        return $currency === 'NGN';
    }

    public function getName(): string
    {
        return 'Paystack';
    }
}
