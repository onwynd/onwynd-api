<?php

namespace App\Services\PaymentService;

use App\Helpers\GatewaySettings;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KlumpService
{
    protected $baseUrl;

    protected $publicKey;

    protected $privateKey;

    public function __construct()
    {
        $this->baseUrl    = config('services.klump.base_url', 'https://api.useklump.com/v1');
        $gw               = GatewaySettings::gateway('klump');
        $this->publicKey  = $gw['public_key'] ?: config('services.klump.public_key', '');
        $this->privateKey = $gw['secret_key'] ?: config('services.klump.secret_key', '');
    }

    /**
     * Initialize a payment
     *
     * @param  float  $amount  Amount
     * @param  string  $email  User email
     * @param  string  $reference  Transaction reference
     * @param  array  $metadata  Additional metadata
     * @return array
     */
    public function initializePayment($amount, $email, $reference, $metadata = [])
    {
        try {
            $payload = [
                'amount' => $amount,
                'currency' => 'NGN',
                'email' => $email,
                'merchant_reference' => $reference,
                'meta_data' => $metadata,
                'redirect_url' => config('services.klump.redirect_url', route('payment.callback')),
                'items' => [
                    [
                        'name' => $metadata['title'] ?? 'Service Payment',
                        'unit_price' => $amount,
                        'quantity' => 1,
                    ],
                ],
            ];

            $response = Http::withHeaders([
                'klump-secret-key' => $this->privateKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/transactions", $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'authorization_url' => $data['data']['url'] ?? $data['url'] ?? '',
                    'reference' => $reference,
                    'gateway_payment_id' => $data['data']['id'] ?? null,
                    'raw_response' => $data,
                ];
            }

            Log::error('Klump Initialization Error: '.$response->body());

            return [
                'success' => false,
                'message' => $response->json()['message'] ?? 'Klump initialization failed',
            ];
        } catch (Exception $e) {
            Log::error('Klump Exception: '.$e->getMessage());

            return ['success' => false, 'message' => 'Payment service error: '.$e->getMessage()];
        }
    }

    /**
     * Verify a payment
     *
     * @param  string  $reference  Transaction reference
     * @return array
     */
    public function verifyPayment($reference)
    {
        try {
            $response = Http::withHeaders([
                'klump-secret-key' => $this->privateKey,
                'Content-Type' => 'application/json',
            ])->get("{$this->baseUrl}/transactions/{$reference}/verify");

            if ($response->successful()) {
                $data = $response->json();
                $status = $data['data']['status'] ?? 'pending';

                return [
                    'success' => true,
                    'status' => $status === 'successful' ? 'completed' : ($status === 'failed' ? 'failed' : 'pending'),
                    'gateway_response' => $data,
                ];
            }

            return ['success' => false, 'message' => 'Verification failed'];
        } catch (Exception $e) {
            Log::error('Klump Verification Exception: '.$e->getMessage());

            return ['success' => false, 'message' => 'Verification service error'];
        }
    }

    /**
     * Handle webhook event
     *
     * @param  array  $payload
     * @return array
     */
    public function handleWebhookEvent($payload)
    {
        // Klump webhook structure handling
        // Assuming event type is in 'event' key
        $event = $payload['event'] ?? 'unknown';

        switch ($event) {
            case 'charge.success':
                return [
                    'success' => true,
                    'event' => 'payment_success',
                    'reference' => $payload['data']['merchant_reference'] ?? null,
                    'gateway_id' => $payload['data']['reference'] ?? null,
                ];
            case 'charge.failed':
                return [
                    'success' => true, // Handled successfully
                    'event' => 'payment_failed',
                    'reference' => $payload['data']['merchant_reference'] ?? null,
                ];
            default:
                return [
                    'success' => true,
                    'event' => 'ignored',
                ];
        }
    }

    /**
     * Refund a payment
     * Klump might not support programmatic refunds via API in the same way,
     * but implementing stub or actual call if docs were available.
     */
    public function refundPayment($transactionId, $amount = null)
    {
        // Placeholder implementation
        return [
            'success' => false,
            'message' => 'Refunds not supported via API for Klump yet.',
        ];
    }
}
