<?php

namespace App\Services\PaymentService;

use App\Helpers\GatewaySettings;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DodoPayments gateway service for international payments.
 * Documentation: https://docs.dodopayments.com
 */
class DodoPaymentsService
{
    private ?string $secretKey;

    private string $baseUrl;

    private bool $isLive;

    public function __construct()
    {
        $gw              = GatewaySettings::gateway('dodopayments');
        $this->secretKey = $gw['secret_key'] ?: config('services.dodopayments.secret_key', '');
        $this->isLive    = $gw['mode'] === 'live';
        $this->baseUrl   = $this->isLive
            ? 'https://api.dodopayments.com'
            : 'https://test.dodopayments.com';

        if (empty($this->secretKey)) {
            throw new Exception('DodoPayments secret key not configured.');
        }
    }

    /**
     * Initialize a payment session.
     *
     * @param  float  $amount  Amount in the smallest currency unit (e.g. cents for USD)
     * @param  string  $currency  ISO 4217 currency code (e.g. USD, GBP, EUR)
     * @param  string  $email  Customer email
     * @param  string  $reference  Unique payment reference
     * @param  array  $metadata  Additional metadata (customer_name, description, etc.)
     * @return array{checkout_url: string, payment_id: string, reference: string}
     *
     * @throws Exception
     */
    public function initializePayment(float $amount, string $currency, string $email, string $reference, array $metadata = []): array
    {
        $payload = [
            'billing' => [
                'email' => $email,
                'name' => $metadata['customer_name'] ?? 'Customer',
            ],
            'payment_link' => true,
            'product_cart' => [
                [
                    'product_id' => $metadata['product_id'] ?? config('services.dodopayments.default_product_id', 'onwynd_service'),
                    'quantity' => 1,
                ],
            ],
            'metadata' => array_merge($metadata, [
                'reference' => $reference,
                'email' => $email,
            ]),
            'return_url' => $metadata['success_url'] ?? config('app.url').'/payment/success',
        ];

        Log::info('DodoPayments: Initializing payment', [
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/payments", $payload);

        if ($response->failed()) {
            Log::error('DodoPayments: Payment init failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception('DodoPayments initialization failed: '.$response->json('message', $response->body()));
        }

        $data = $response->json();

        return [
            'checkout_url' => $data['payment_link'] ?? $data['url'] ?? '',
            'payment_id' => $data['payment_id'] ?? $data['id'] ?? '',
            'reference' => $reference,
            'gateway_response' => $data,
        ];
    }

    /**
     * Verify / retrieve a payment by its DodoPayments payment ID.
     *
     * @param  string  $paymentId  DodoPayments payment ID
     *
     * @throws Exception
     */
    public function verifyPayment(string $paymentId): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/payments/{$paymentId}");

        if ($response->failed()) {
            throw new Exception('DodoPayments: Could not verify payment '.$paymentId);
        }

        $data = $response->json();
        $status = $data['status'] ?? 'unknown';

        return [
            'success' => in_array($status, ['succeeded', 'paid', 'complete', 'completed']),
            'status' => $status,
            'payment_id' => $paymentId,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'gateway_response' => $data,
        ];
    }

    /**
     * Issue a full or partial refund.
     *
     * @param  string  $paymentId  DodoPayments payment ID
     * @param  float|null  $amount  Amount to refund (null = full refund)
     * @param  string  $reason  Reason for the refund
     *
     * @throws Exception
     */
    public function refundPayment(string $paymentId, ?float $amount = null, string $reason = 'requested_by_customer'): array
    {
        $payload = ['reason' => $reason];
        if ($amount !== null) {
            $payload['amount'] = (int) ($amount * 100); // smallest unit
        }

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/refunds", array_merge($payload, ['payment_id' => $paymentId]));

        if ($response->failed()) {
            throw new Exception('DodoPayments: Refund failed — '.$response->json('message', $response->body()));
        }

        return $response->json();
    }

    /**
     * Validate a webhook signature from DodoPayments.
     *
     * @param  string  $payload  Raw request body
     * @param  string  $signature  Value of the Webhook-Signature header
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $secret = config('services.dodopayments.webhook_secret', '');
        $computed = hash_hmac('sha256', $payload, $secret);

        return hash_equals($computed, $signature);
    }
}
