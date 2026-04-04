<?php

namespace App\Services\PaymentService;

use App\Helpers\GatewaySettings;
use App\Models\Payment;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterWaveService
{
    private $publicKey;

    private $secretKey;

    private $baseUrl = 'https://api.flutterwave.com/v3';

    private $maxRetries = 3;

    private $retryDelay = 1000;

    public function __construct()
    {
        $gw = GatewaySettings::gateway('flutterwave');
        $this->publicKey = $gw['public_key'] ?: config('services.flutterwave.public_key', '');
        $this->secretKey = $gw['secret_key'] ?: config('services.flutterwave.secret_key', '');

        if (! $this->publicKey || ! $this->secretKey) {
            throw new Exception('Flutterwave configuration missing. Add keys via Admin → Settings → Gateways.');
        }
    }

    /**
     * Initialize payment and return payment link
     *
     * @param  float  $amount  Amount in Naira
     * @param  string  $email  Customer email
     * @param  string  $reference  Unique transaction reference
     * @param  array  $metadata  Additional data
     * @return array Payment initialization response
     *
     * @throws Exception
     */
    public function initializePayment(float $amount, string $email, string $reference, array $metadata = []): array
    {
        try {
            if ($amount < 100) {
                throw new Exception('Payment amount must be at least ₦100');
            }

            if ($amount > 100000000) {
                throw new Exception('Payment amount exceeds maximum limit');
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address provided');
            }

            $payload = [
                'tx_ref' => $reference,
                'amount' => $amount,
                'currency' => 'NGN',
                'customer' => [
                    'email' => $email,
                    'name' => $metadata['customer_name'] ?? 'Customer',
                ],
                'customizations' => [
                    'title' => $metadata['title'] ?? 'ONWYND Payment',
                    'description' => $metadata['description'] ?? 'Therapy session payment',
                    'logo' => env('APP_LOGO_URL', ''),
                ],
                'redirect_url' => route('payment.callback'),
                'meta' => $metadata,
            ];

            Log::info('Flutterwave: Initializing payment', [
                'reference' => $reference,
                'amount' => $amount,
                'email' => $email,
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl.'/payments', $payload);

            /** @phpstan-ignore-next-line */
            if (! $response->successful()) {
                Log::error('Flutterwave initialization failed', [
                    /** @phpstan-ignore-next-line */
                    'status' => $response->status(),
                    /** @phpstan-ignore-next-line */
                    'body' => $response->body(),
                ]);

                /** @phpstan-ignore-next-line */
                throw new Exception('Failed to initialize payment: '.$response->json()['message'] ?? 'Unknown error');
            }

            /** @phpstan-ignore-next-line */
            $data = $response->json()['data'];

            Log::info('Flutterwave: Payment initialized successfully', [
                'reference' => $reference,
                'link' => $data['link'],
            ]);

            return [
                'success' => true,
                'authorization_url' => $data['link'],
                'reference' => $reference,
                'amount' => $amount,
                'gateway' => 'flutterwave',
            ];

        } catch (Exception $e) {
            Log::error('Flutterwave initialization exception', [
                'message' => $e->getMessage(),
                'reference' => $reference,
            ]);

            throw $e;
        }
    }

    /**
     * Verify payment with Flutterwave
     *
     * @param  string  $reference  Transaction reference
     * @return array Payment verification details
     *
     * @throws Exception
     */
    public function verifyPayment(string $reference): array
    {
        try {
            if (empty($reference)) {
                throw new Exception('Transaction reference is required');
            }

            Log::info('Flutterwave: Verifying payment', ['reference' => $reference]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
            ])->get($this->baseUrl.'/transactions/verify_by_reference', [
                'tx_ref' => $reference,
            ]);

            if (! $response->successful()) {
                Log::error('Flutterwave verification failed', [
                    'status' => $response->status(),
                    'reference' => $reference,
                ]);

                throw new Exception('Failed to verify payment');
            }

            $data = $response->json()['data'];
            $transaction = $data['data'][0] ?? $data;

            $status = $this->mapFlutterWaveStatus($transaction['status']);

            $verificationData = [
                'success' => $transaction['status'] === 'successful',
                'reference' => $reference,
                'transaction_id' => $transaction['id'],
                'amount' => $transaction['amount'],
                'currency' => $transaction['currency'],
                'status' => $status,
                'customer_email' => $transaction['customer']['email'] ?? null,
                'paid_at' => $transaction['created_at'] ?? null,
                'gateway' => 'flutterwave',
                'raw_response' => $transaction,
            ];

            Log::info('Flutterwave: Payment verified', [
                'reference' => $reference,
                'status' => $status,
                'amount' => $verificationData['amount'],
            ]);

            return $verificationData;

        } catch (Exception $e) {
            Log::error('Flutterwave verification exception', [
                'message' => $e->getMessage(),
                'reference' => $reference,
            ]);

            throw $e;
        }
    }

    /**
     * Verify webhook signature from Flutterwave
     *
     * @param  string  $signature  Signature from webhook header
     * @param  string  $body  Raw request body
     */
    public function verifyWebhookSignature(string $signature, string $body): bool
    {
        try {
            $hash = hash_hmac('sha256', $body, $this->secretKey);
            $isValid = hash_equals($hash, $signature);

            Log::info('Flutterwave: Webhook signature verification', [
                'valid' => $isValid,
            ]);

            return $isValid;

        } catch (Exception $e) {
            Log::error('Flutterwave: Webhook signature verification failed', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Handle Flutterwave webhook event
     *
     * @param  array  $data  Webhook payload
     * @return array Processing result
     */
    public function handleWebhookEvent(array $data): array
    {
        try {
            $event = $data['event'] ?? null;
            $transaction = $data['data'] ?? [];

            Log::info('Flutterwave: Processing webhook event', [
                'event' => $event,
                'reference' => $transaction['tx_ref'] ?? null,
            ]);

            if ($event === 'charge.completed') {
                return $this->handleSuccessfulPayment($transaction);
            } elseif ($event === 'charge.failed') {
                return $this->handleFailedPayment($transaction);
            }

            Log::warning('Flutterwave: Unhandled webhook event', ['event' => $event]);

            return [
                'success' => true,
                'message' => 'Event acknowledged',
            ];

        } catch (Exception $e) {
            Log::error('Flutterwave: Webhook processing exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process refund for a transaction
     *
     * @param  string  $transactionId  Flutterwave transaction ID
     * @param  float|null  $amount  Amount to refund (null for full)
     * @return array Refund response
     *
     * @throws Exception
     */
    public function refundPayment(string $transactionId, ?float $amount = null): array
    {
        try {
            Log::info('Flutterwave: Processing refund', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
            ]);

            $payload = [];
            if ($amount) {
                $payload['amount'] = $amount;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl.'/transactions/'.$transactionId.'/refund', $payload);

            if (! $response->successful()) {
                Log::error('Flutterwave refund failed', [
                    'status' => $response->status(),
                    'transaction_id' => $transactionId,
                ]);

                throw new Exception('Refund processing failed: '.$response->json()['message'] ?? 'Unknown error');
            }

            $refundData = $response->json()['data'];

            Log::info('Flutterwave: Refund processed successfully', [
                'transaction_id' => $transactionId,
            ]);

            return [
                'success' => true,
                'refund_status' => $refundData['status'],
                'original_transaction_id' => $transactionId,
                'gateway' => 'flutterwave',
            ];

        } catch (Exception $e) {
            Log::error('Flutterwave refund exception', [
                'message' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);

            throw $e;
        }
    }

    /**
     * Create payout to bank account (for paying therapists)
     *
     * @param  string  $accountNumber  Bank account number
     * @param  string  $bankCode  Bank code
     * @param  float  $amount  Amount in Naira
     * @param  string  $reference  Unique reference
     * @param  string  $accountName  Account holder name
     * @return array Payout response
     *
     * @throws Exception
     */
    public function createPayout(string $accountNumber, string $bankCode, float $amount, string $reference, string $accountName): array
    {
        try {
            if ($amount < 100) {
                throw new Exception('Payout amount must be at least ₦100');
            }

            Log::info('Flutterwave: Creating payout', [
                'account' => substr($accountNumber, -4),
                'amount' => $amount,
                'reference' => $reference,
            ]);

            $payload = [
                'account_bank' => $bankCode,
                'account_number' => $accountNumber,
                'amount' => $amount,
                'currency' => 'NGN',
                'beneficiary_name' => $accountName,
                'reference' => $reference,
                'debit_currency' => 'NGN',
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl.'/transfers', $payload);

            if (! $response->successful()) {
                Log::error('Flutterwave payout failed', [
                    'status' => $response->status(),
                    'reference' => $reference,
                ]);

                throw new Exception('Payout creation failed: '.$response->json()['message'] ?? 'Unknown error');
            }

            $payoutData = $response->json()['data'];

            Log::info('Flutterwave: Payout created successfully', [
                'reference' => $reference,
                'transfer_id' => $payoutData['id'],
            ]);

            return [
                'success' => true,
                'payout_reference' => $reference,
                'transfer_id' => $payoutData['id'],
                'amount' => $amount,
                'status' => $payoutData['status'],
                'gateway' => 'flutterwave',
            ];

        } catch (Exception $e) {
            Log::error('Flutterwave payout exception', [
                'message' => $e->getMessage(),
                'reference' => $reference,
            ]);

            throw $e;
        }
    }

    /**
     * Verify bank account details
     *
     * @param  string  $accountNumber  Account number
     * @param  string  $bankCode  Bank code
     * @return array Verification response
     *
     * @throws Exception
     */
    public function verifyAccountNumber(string $accountNumber, string $bankCode): array
    {
        try {
            Log::info('Flutterwave: Verifying account', [
                'account' => substr($accountNumber, -4),
                'bank_code' => $bankCode,
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
            ])->get($this->baseUrl.'/accounts/resolve', [
                'account_number' => $accountNumber,
                'account_bank' => $bankCode,
            ]);

            if (! $response->successful()) {
                Log::error('Flutterwave account verification failed', [
                    'status' => $response->status(),
                ]);

                throw new Exception('Account verification failed');
            }

            $data = $response->json()['data'];

            Log::info('Flutterwave: Account verified', [
                'account_name' => $data['account_name'] ?? 'N/A',
            ]);

            return [
                'success' => true,
                'account_name' => $data['account_name'] ?? null,
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ];

        } catch (Exception $e) {
            Log::error('Flutterwave account verification exception', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get list of Nigerian banks
     *
     * @return array List of banks
     *
     * @throws Exception
     */
    public function getBankList(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
            ])->get($this->baseUrl.'/banks/NG');

            if (! $response->successful()) {
                throw new Exception('Failed to retrieve bank list');
            }

            return $response->json()['data'] ?? [];

        } catch (Exception $e) {
            Log::error('Flutterwave bank list retrieval failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Map Flutterwave status to internal status
     *
     * @param  string  $flutterWaveStatus  Status from Flutterwave
     * @return string Internal status
     */
    private function mapFlutterWaveStatus(string $flutterWaveStatus): string
    {
        $statusMap = [
            'successful' => 'completed',
            'failed' => 'failed',
            'pending' => 'pending',
            'cancelled' => 'cancelled',
        ];

        return $statusMap[$flutterWaveStatus] ?? $flutterWaveStatus;
    }

    /**
     * Handle successful payment from webhook
     *
     * @param  array  $data  Payment data
     * @return array Processing result
     */
    private function handleSuccessfulPayment(array $data): array
    {
        try {
            Log::info('Flutterwave: Handling successful payment', [
                'reference' => $data['tx_ref'] ?? null,
            ]);

            $payment = Payment::where('payment_reference', $data['tx_ref'] ?? '')
                ->orWhere('gateway_payment_id', $data['id'] ?? '')
                ->first();

            if ($payment) {
                $payment->update([
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'completed_at' => now(),
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'webhook_processed' => true,
                        'transaction_id' => $data['id'] ?? null,
                    ]),
                ]);

                Log::info('Flutterwave: Payment record updated', [
                    'payment_id' => $payment->id,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Payment processed successfully',
            ];

        } catch (Exception $e) {
            Log::error('Flutterwave: Failed to handle successful payment', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle failed payment from webhook
     *
     * @param  array  $data  Payment data
     * @return array Processing result
     */
    private function handleFailedPayment(array $data): array
    {
        try {
            Log::info('Flutterwave: Handling failed payment', [
                'reference' => $data['tx_ref'] ?? null,
            ]);

            $payment = Payment::where('payment_reference', $data['tx_ref'] ?? '')
                ->orWhere('gateway_payment_id', $data['id'] ?? '')
                ->first();

            if ($payment) {
                $payment->update([
                    'status' => 'failed',
                    'payment_status' => 'failed',
                    'failed_at' => now(),
                    'failure_reason' => $data['processor_response'] ?? 'Payment declined',
                ]);

                Log::info('Flutterwave: Payment marked as failed', [
                    'payment_id' => $payment->id,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Failed payment recorded',
            ];

        } catch (Exception $e) {
            Log::error('Flutterwave: Failed to handle failed payment', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
