<?php

namespace App\Services\PaymentService;

use App\Models\Payment;
use App\Models\Payment\Subscription as PaymentSubscription;
use App\Models\User;
use App\Helpers\GatewaySettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected $baseUrl;

    protected $secretKey;

    protected $mode;

    public function __construct()
    {
        $this->baseUrl   = 'https://api.paystack.co';
        $this->mode      = GatewaySettings::mode('paystack');
        $this->secretKey = GatewaySettings::secretKey('paystack', config('services.paystack.secret_key', ''));
    }

    public function initializeTransaction($amount, $email, $metadata = [])
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/transaction/initialize", [
                'amount' => $amount,
                'email' => $email,
                'metadata' => $metadata,
                'callback_url' => config('services.paystack.callback_url'),
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack Initialization Error: '.$response->body());

            return ['status' => false, 'message' => 'Transaction initialization failed'];
        } catch (\Exception $e) {
            Log::error('Paystack Exception: '.$e->getMessage());

            return ['status' => false, 'message' => 'Payment service error'];
        }
    }

    public function initializePayment($amount, $email, $reference, $metadata = []): array
    {
        try {
            $payload = [
                'amount'       => (int) ($amount * 100),
                'email'        => $email,
                'reference'    => $reference,
                'metadata'     => $metadata,
                'callback_url' => config('services.paystack.callback_url'),
            ];
            // Pass currency explicitly so Paystack routes USD charges correctly
            // when the account is enabled for multi-currency.
            if (!empty($metadata['currency']) && strtoupper($metadata['currency']) !== 'NGN') {
                $payload['currency'] = strtoupper($metadata['currency']);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type'  => 'application/json',
            ])->post("{$this->baseUrl}/transaction/initialize", $payload);

            if ($response->successful() && $response->json('status')) {
                $data = $response->json('data');

                return [
                    'success' => true,
                    'authorization_url' => $data['authorization_url'] ?? null,
                    'access_code' => $data['access_code'] ?? null,
                    'gateway_payment_id' => $data['reference'] ?? $reference,
                    'reference' => $reference,
                ];
            }

            Log::error('Paystack initializePayment Error: '.$response->body());

            return ['success' => false, 'message' => $response->json('message') ?? 'Transaction initialization failed'];
        } catch (\Exception $e) {
            Log::error('Paystack initializePayment Exception: '.$e->getMessage());

            return ['success' => false, 'message' => 'Payment service error'];
        }
    }

    public function verifyPayment($reference): array
    {
        $result = $this->verifyTransaction($reference);
        if (isset($result['data']['status']) && $result['data']['status'] === 'success') {
            return [
                'success' => true,
                'status' => 'success',
                'amount' => ($result['data']['amount'] ?? 0) / 100,
                'currency' => $result['data']['currency'] ?? 'NGN',
                'paid_at' => $result['data']['paid_at'] ?? null,
                'gateway_payment_id' => $result['data']['id'] ?? null,
            ];
        }

        return [
            'success' => false,
            'status' => $result['data']['status'] ?? 'failed',
            'message' => $result['message'] ?? 'Verification failed',
        ];
    }

    public function refundPayment($transactionId, $amount = null): array
    {
        try {
            $payload = ['transaction' => $transactionId];
            if ($amount !== null) {
                $payload['amount'] = (int) ($amount * 100);
            }
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/refund", $payload);

            if ($response->successful() && $response->json('status')) {
                return ['success' => true, 'data' => $response->json('data')];
            }

            return ['success' => false, 'message' => $response->json('message') ?? 'Refund failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function verifyTransaction($reference)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->get("{$this->baseUrl}/transaction/verify/{$reference}");

            if ($response->successful()) {
                return $response->json();
            }

            return ['status' => false, 'message' => 'Verification failed'];
        } catch (\Exception $e) {
            Log::error('Paystack Verification Exception: '.$e->getMessage());

            return ['status' => false, 'message' => 'Verification service error'];
        }
    }

    public function verifyWebhookSignature(string $signature, string $body): bool
    {
        try {
            if (! $this->secretKey || ! $signature) {
                return false;
            }
            $computed = hash_hmac('sha512', $body, $this->secretKey);
            $valid = hash_equals($computed, $signature);
            Log::info('Paystack: Webhook signature check', ['valid' => $valid]);

            return $valid;
        } catch (\Throwable $e) {
            Log::error('Paystack: Signature verification error', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Create a Paystack transfer recipient.
     *
     * @param  string  $accountNumber  Bank account number
     * @param  string  $bankCode  Paystack bank code (e.g. "058" for GTBank)
     * @param  string  $accountName  Account holder name
     * @param  string  $currency  Currency code (default NGN)
     * @return array{success:bool, recipient_code?:string, message?:string}
     */
    public function createTransferRecipient(
        string $accountNumber,
        string $bankCode,
        string $accountName,
        string $currency = 'NGN'
    ): array {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/transferrecipient", [
                'type' => 'nuban',
                'name' => $accountName,
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'currency' => $currency,
            ]);

            Log::info('Paystack createTransferRecipient', ['status' => $response->status()]);

            if ($response->successful() && $response->json('status')) {
                $recipientCode = $response->json('data.recipient_code');

                return ['success' => true, 'recipient_code' => $recipientCode];
            }

            Log::error('Paystack createTransferRecipient failed', ['body' => $response->body()]);

            return ['success' => false, 'message' => $response->json('message') ?? 'Failed to create transfer recipient'];
        } catch (\Throwable $e) {
            Log::error('Paystack createTransferRecipient exception', ['message' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Initiate a Paystack transfer (payout to therapist).
     *
     * @param  int  $amountKobo  Amount in kobo (Naira * 100)
     * @param  string  $recipientCode  Paystack recipient code (RCP_xxx)
     * @param  string  $reference  Unique transfer reference
     * @param  string  $reason  Human-readable reason for the transfer
     * @return array{success:bool, transfer_code?:string, status?:string, message?:string}
     */
    public function initiateTransfer(
        int $amountKobo,
        string $recipientCode,
        string $reference,
        string $reason
    ): array {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/transfer", [
                'source' => 'balance',
                'amount' => $amountKobo,
                'recipient' => $recipientCode,
                'reference' => $reference,
                'reason' => $reason,
            ]);

            Log::info('Paystack initiateTransfer', ['status' => $response->status(), 'ref' => $reference]);

            if ($response->successful() && $response->json('status')) {
                $data = $response->json('data');

                return [
                    'success' => true,
                    'transfer_code' => $data['transfer_code'] ?? null,
                    'status' => $data['status'] ?? 'pending',
                    'reference' => $reference,
                ];
            }

            Log::error('Paystack initiateTransfer failed', ['body' => $response->body()]);

            return ['success' => false, 'message' => $response->json('message') ?? 'Transfer initiation failed'];
        } catch (\Throwable $e) {
            Log::error('Paystack initiateTransfer exception', ['message' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify a Paystack transfer by transfer code.
     *
     * @param  string  $transferCode  Paystack transfer code (TRF_xxx)
     * @return array{success:bool, status?:string, amount?:int, message?:string}
     */
    public function verifyTransfer(string $transferCode): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->get("{$this->baseUrl}/transfer/{$transferCode}");

            Log::info('Paystack verifyTransfer', ['code' => $transferCode, 'status' => $response->status()]);

            if ($response->successful() && $response->json('status')) {
                $data = $response->json('data');

                return [
                    'success' => true,
                    'status' => $data['status'] ?? 'unknown',
                    'amount' => $data['amount'] ?? 0,
                ];
            }

            return ['success' => false, 'message' => $response->json('message') ?? 'Transfer verification failed'];
        } catch (\Throwable $e) {
            Log::error('Paystack verifyTransfer exception', ['message' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleWebhookEvent(array $payload): array
    {
        try {
            $event = $payload['event'] ?? null;
            $data = $payload['data'] ?? [];
            $reference = $data['reference'] ?? $data['tx_ref'] ?? $data['invoice_code'] ?? null;
            Log::info('Paystack: Handling webhook', ['event' => $event, 'reference' => $reference]);

            if ($event === 'charge.success') {
                if ($reference) {
                    $payment = Payment::where('payment_reference', $reference)->first();
                    if ($payment) {
                        $payment->markAsCompleted();
                    }
                }

                return ['success' => true, 'message' => 'charge.success processed'];
            }

            if (in_array($event, ['charge.failed', 'invoice.payment_failed'])) {
                $this->downgradeUser($payload);
                if ($reference) {
                    $payment = Payment::where('payment_reference', $reference)->first();
                    if ($payment) {
                        $payment->markAsFailed($data['gateway_response'] ?? 'Payment failed');
                    }
                }

                return ['success' => true, 'message' => $event.' processed'];
            }

            return ['success' => true, 'message' => 'Event acknowledged'];
        } catch (\Throwable $e) {
            Log::error('Paystack: Webhook handling error', ['message' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function downgradeUser(array $payload): void
    {
        try {
            $email = $payload['data']['customer']['email'] ?? $payload['data']['customer_email'] ?? null;
            $reference = $payload['data']['reference'] ?? null;
            $user = null;

            if ($reference) {
                $payment = Payment::where('payment_reference', $reference)->first();
                if ($payment && $payment->user) {
                    $user = $payment->user;
                }
            }
            if (! $user && $email) {
                $user = User::where('email', $email)->first();
            }
            if (! $user) {
                Log::warning('Paystack: Downgrade user not found', ['reference' => $reference, 'email' => $email]);

                return;
            }

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

            Log::info('Paystack: User downgraded on payment failure', ['user_id' => $user->id]);
        } catch (\Throwable $e) {
            Log::error('Paystack: Failed to downgrade user', ['message' => $e->getMessage()]);
        }
    }
}
