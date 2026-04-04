<?php

namespace App\Services\PaymentService;

use App\Helpers\GatewaySettings;
use App\Models\Payment;
use App\Models\Therapist;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentProcessor
{
    private $paystackService;

    private $flutterWaveService;

    private $stripeService;

    private $klumpService;

    private $dodoPaymentsService;

    private $maxRetries = 3;

    public function __construct()
    {
        try {
            $this->paystackService = new PaystackService;
        } catch (Exception $e) {
            Log::warning('Paystack service not initialized: '.$e->getMessage());
            $this->paystackService = null;
        }

        try {
            $this->flutterWaveService = new FlutterWaveService;
        } catch (Exception $e) {
            Log::warning('Flutterwave service not initialized: '.$e->getMessage());
            $this->flutterWaveService = null;
        }

        try {
            $this->stripeService = new StripeService;
        } catch (Exception $e) {
            Log::warning('Stripe service not initialized: '.$e->getMessage());
            $this->stripeService = null;
        }

        try {
            $this->klumpService = new KlumpService;
        } catch (Exception $e) {
            Log::warning('Klump service not initialized: '.$e->getMessage());
            $this->klumpService = null;
        }

        try {
            $this->dodoPaymentsService = new DodoPaymentsService;
        } catch (Exception $e) {
            Log::warning('DodoPayments service not initialized: '.$e->getMessage());
            $this->dodoPaymentsService = null;
        }
    }

    /**
     * Process payment through appropriate gateway
     *
     * @param  Payment  $payment  Payment model instance
     * @param  array  $metadata  Additional metadata
     * @return array Payment initialization response
     *
     * @throws Exception
     */
    public function processPayment(Payment $payment, array $metadata = []): array
    {
        return DB::transaction(function () use ($payment, $metadata) {
            try {
                if (! $payment->amount || $payment->amount <= 0) {
                    throw new Exception('Invalid payment amount');
                }

                // Handle anonymous payments
                $isAnonymous = $metadata['is_anonymous'] ?? false;
                $customerEmail = $payment->user ? $payment->user->email : ($metadata['customer_email'] ?? null);
                $customerName = $metadata['customer_name'] ?? ($payment->user ? $payment->user->full_name : 'Anonymous User');

                if (! $isAnonymous && (! $payment->user || ! $payment->user->email)) {
                    throw new Exception('User email is required for payment processing');
                }

                if ($isAnonymous && ! $customerEmail) {
                    throw new Exception('Customer email is required for anonymous payment processing');
                }

                Log::info('Payment Processor: Starting payment processing', [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ]);

                // Determine the appropriate gateway
                $gateway = $this->selectGateway($payment);

                Log::info('Payment Processor: Selected gateway', [
                    'payment_id' => $payment->id,
                    'gateway' => $gateway,
                ]);

                // Generate unique reference
                $reference = $this->generatePaymentReference($payment);

                // Initialize payment with selected gateway
                $response = $this->initializeWithGateway($gateway, $payment, $reference, $metadata);

                if (! $response['success']) {
                    throw new Exception($response['message'] ?? 'Payment initialization failed');
                }

                // Update payment record
                $payment->update([
                    'payment_gateway' => $gateway,
                    'payment_reference' => $reference,
                    'gateway_payment_id' => $response['gateway_payment_id'] ?? null,
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'initiated_at' => now(),
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'initialization_response' => $response,
                    ]),
                ]);

                Log::info('Payment Processor: Payment initialized successfully', [
                    'payment_id' => $payment->id,
                    'reference' => $reference,
                    'gateway' => $gateway,
                ]);

                return [
                    'success' => true,
                    'authorization_url' => $response['authorization_url'] ?? $response['checkout_url'] ?? null,
                    'access_code' => $response['access_code'] ?? null,
                    'reference' => $reference,
                    'gateway' => $gateway,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ];

            } catch (Exception $e) {
                Log::error('Payment Processor: Payment processing failed', [
                    'payment_id' => $payment->id ?? null,
                    'message' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Verify payment with payment gateway
     *
     * @param  Payment  $payment  Payment model instance
     * @return array Verification result
     *
     * @throws Exception
     */
    public function verifyPayment(Payment $payment): array
    {
        return DB::transaction(function () use ($payment) {
            try {
                if (! $payment->payment_gateway || ! $payment->payment_reference) {
                    throw new Exception('Invalid payment reference or gateway information');
                }

                Log::info('Payment Processor: Starting payment verification', [
                    'payment_id' => $payment->id,
                    'gateway' => $payment->payment_gateway,
                    'reference' => $payment->payment_reference,
                ]);

                $service = $this->getGatewayService($payment->payment_gateway);

                if (! $service) {
                    throw new Exception('Gateway service not available: '.$payment->payment_gateway);
                }

                $verificationData = $service->verifyPayment($payment->payment_reference);

                if (! $verificationData['success']) {
                    Log::warning('Payment Processor: Payment verification returned unsuccessful', [
                        'payment_id' => $payment->id,
                        'reference' => $payment->payment_reference,
                    ]);
                }

                // Update payment record with verification details
                $this->updatePaymentFromVerification($payment, $verificationData);

                Log::info('Payment Processor: Payment verified', [
                    'payment_id' => $payment->id,
                    'status' => $verificationData['status'],
                ]);

                return $verificationData;

            } catch (Exception $e) {
                Log::error('Payment Processor: Payment verification failed', [
                    'payment_id' => $payment->id ?? null,
                    'message' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Process refund for a payment
     *
     * @param  Payment  $payment  Payment to refund
     * @param  float|null  $amount  Amount to refund (null for full refund)
     * @return array Refund response
     *
     * @throws Exception
     */
    public function refundPayment(Payment $payment, ?float $amount = null): array
    {
        return DB::transaction(function () use ($payment, $amount) {
            try {
                if ($payment->status !== 'completed') {
                    throw new Exception('Only completed payments can be refunded');
                }

                if ($amount && $amount > $payment->amount) {
                    throw new Exception('Refund amount cannot exceed payment amount');
                }

                Log::info('Payment Processor: Processing refund', [
                    'payment_id' => $payment->id,
                    'amount' => $amount ?? $payment->amount,
                    'gateway' => $payment->payment_gateway,
                ]);

                $service = $this->getGatewayService($payment->payment_gateway);

                if (! $service) {
                    throw new Exception('Gateway service not available for refund');
                }

                // Get the correct transaction ID from the payment gateway
                $transactionId = $payment->gateway_payment_id ?? $payment->payment_reference;

                $refundResponse = match ($payment->payment_gateway) {
                    'paystack' => $this->paystackService->refundPayment($transactionId, $amount),
                    'flutterwave' => $this->flutterWaveService->refundPayment($transactionId, $amount),
                    'stripe' => $this->stripeService->refundPayment($transactionId, $amount),
                    'klump' => $this->klumpService->refundPayment($transactionId, $amount),
                    'dodopayments' => $this->dodoPaymentsService->refundPayment($transactionId, null, 'requested_by_customer'),
                    default => throw new Exception('Unsupported gateway for refund')
                };

                if (! $refundResponse['success']) {
                    throw new Exception('Refund processing failed: '.$refundResponse['message'] ?? 'Unknown error');
                }

                // Create refund record
                $payment->refunds()->create([
                    'amount' => $amount ?? $payment->amount,
                    'reason' => 'customer_request',
                    'status' => 'completed',
                    'processed_at' => now(),
                    'metadata' => [
                        'gateway_response' => $refundResponse,
                    ],
                ]);

                // Update payment status if fully refunded
                if (! $amount || $amount === $payment->amount) {
                    $payment->update([
                        'status' => 'refunded',
                        'payment_status' => 'refunded',
                    ]);
                }

                Log::info('Payment Processor: Refund processed successfully', [
                    'payment_id' => $payment->id,
                    'amount' => $amount ?? $payment->amount,
                ]);

                return [
                    'success' => true,
                    'refund_amount' => $amount ?? $payment->amount,
                    'payment_id' => $payment->id,
                    'gateway' => $payment->payment_gateway,
                ];

            } catch (Exception $e) {
                Log::error('Payment Processor: Refund processing failed', [
                    'payment_id' => $payment->id ?? null,
                    'message' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Get transaction status
     *
     * @param  Payment  $payment  Payment model instance
     * @return array Transaction status
     */
    public function getTransactionStatus(Payment $payment): array
    {
        try {
            Log::info('Payment Processor: Checking transaction status', [
                'payment_id' => $payment->id,
                'gateway' => $payment->payment_gateway,
            ]);

            return [
                'payment_id' => $payment->id,
                'reference' => $payment->payment_reference,
                'gateway' => $payment->payment_gateway,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'initiated_at' => $payment->initiated_at,
                'completed_at' => $payment->completed_at,
                'failed_at' => $payment->failed_at,
            ];

        } catch (Exception $e) {
            Log::error('Payment Processor: Failed to get transaction status', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Pay therapist from platform balance
     *
     * @param  Therapist  $therapist  Therapist to pay
     * @param  float  $amount  Amount in Naira
     * @param  string  $reason  Payment reason
     * @return array Payout response
     *
     * @throws Exception
     */
    public function payTherapist(Therapist $therapist, float $amount, string $reason = 'session_payment'): array
    {
        return DB::transaction(function () use ($therapist, $amount, $reason) {
            try {
                if ($amount <= 0) {
                    throw new Exception('Payout amount must be greater than zero');
                }

                Log::info('Payment Processor: Processing therapist payout', [
                    'therapist_id' => $therapist->id,
                    'amount' => $amount,
                    'reason' => $reason,
                ]);

                // Verify therapist has valid bank account
                $bankAccount = $therapist->bankAccount;

                if (! $bankAccount) {
                    throw new Exception('Therapist does not have a verified bank account');
                }

                // Select preferred gateway (primary = Paystack for Nigerian therapists)
                $gateway = $therapist->preferred_payout_gateway ?? 'paystack';

                // Generate payout reference
                $reference = $this->generatePayoutReference($therapist);

                // Create payout record first
                $payout = $therapist->payouts()->create([
                    'amount' => $amount,
                    'currency' => 'NGN',
                    'status' => 'pending',
                    'payment_gateway' => $gateway,
                    'payment_reference' => $reference,
                    'reason' => $reason,
                    'initiated_at' => now(),
                ]);

                // Process payout with selected gateway
                $payoutResponse = $this->processPayoutWithGateway(
                    $gateway,
                    $bankAccount->account_number,
                    $bankAccount->bank_code,
                    $amount,
                    $reference,
                    $bankAccount->account_name ?? $therapist->full_name
                );

                if (! $payoutResponse['success']) {
                    $payout->update([
                        'status' => 'failed',
                        'failure_reason' => $payoutResponse['message'] ?? 'Payout failed',
                    ]);

                    throw new Exception('Payout processing failed: '.$payoutResponse['message'] ?? 'Unknown error');
                }

                // Update payout record with gateway response
                $payout->update([
                    'status' => 'completed',
                    'gateway_payment_id' => $payoutResponse['transfer_id'] ?? null,
                    'completed_at' => now(),
                    'metadata' => [
                        'gateway_response' => $payoutResponse,
                    ],
                ]);

                Log::info('Payment Processor: Therapist payout completed', [
                    'therapist_id' => $therapist->id,
                    'payout_id' => $payout->id,
                    'amount' => $amount,
                ]);

                return [
                    'success' => true,
                    'payout_id' => $payout->id,
                    'amount' => $amount,
                    'reference' => $reference,
                    'gateway' => $gateway,
                ];

            } catch (Exception $e) {
                Log::error('Payment Processor: Therapist payout failed', [
                    'therapist_id' => $therapist->id ?? null,
                    'message' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Handle webhook from payment gateway
     *
     * @param  string  $gateway  Gateway name
     * @param  array  $data  Webhook payload
     * @return array Processing result
     */
    public function handleWebhook(string $gateway, array $data): array
    {
        try {
            Log::info('Payment Processor: Handling webhook', [
                'gateway' => $gateway,
            ]);

            $service = $this->getGatewayService($gateway);

            if (! $service) {
                throw new Exception('Gateway service not available: '.$gateway);
            }

            return $service->handleWebhookEvent($data);

        } catch (Exception $e) {
            Log::error('Payment Processor: Webhook handling failed', [
                'gateway' => $gateway,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reconcile payments with gateway
     *
     * @param  string  $gateway  Gateway to reconcile with
     * @param  \Carbon\Carbon  $startDate  Start date
     * @param  \Carbon\Carbon  $endDate  End date
     * @return array Reconciliation result
     */
    public function reconcilePayments(string $gateway, $startDate, $endDate): array
    {
        try {
            Log::info('Payment Processor: Starting payment reconciliation', [
                'gateway' => $gateway,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            $payments = Payment::where('payment_gateway', $gateway)
                ->whereBetween('initiated_at', [$startDate, $endDate])
                ->get();

            $reconciled = 0;
            $failed = 0;
            $issues = [];

            foreach ($payments as $payment) {
                try {
                    $verification = $this->verifyPayment($payment);

                    if ($verification['success'] && $payment->status !== 'completed') {
                        $reconciled++;
                    }
                } catch (Exception $e) {
                    $failed++;
                    $issues[] = [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            Log::info('Payment Processor: Reconciliation completed', [
                'gateway' => $gateway,
                'reconciled' => $reconciled,
                'failed' => $failed,
                'total' => count($payments),
            ]);

            return [
                'success' => true,
                'gateway' => $gateway,
                'reconciled' => $reconciled,
                'failed' => $failed,
                'total' => count($payments),
                'issues' => $issues,
            ];

        } catch (Exception $e) {
            Log::error('Payment Processor: Reconciliation failed', [
                'gateway' => $gateway,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Select appropriate payment gateway based on payment details
     *
     * @param  Payment  $payment  Payment model instance
     * @return string Selected gateway name
     */
    /**
     * Return true when a gateway is both admin-enabled and has a service instance.
     */
    private function gatewayAvailable(string $name): bool
    {
        if (! GatewaySettings::enabled($name)) {
            return false;
        }

        return match ($name) {
            'paystack'     => $this->paystackService !== null,
            'flutterwave'  => $this->flutterWaveService !== null,
            'stripe'       => $this->stripeService !== null,
            'klump'        => $this->klumpService !== null,
            'dodopayments' => $this->dodoPaymentsService !== null,
            default        => false,
        };
    }

    private function selectGateway(Payment $payment): string
    {
        // If payment explicitly specifies an enabled gateway, honour it
        if ($payment->payment_gateway && $this->gatewayAvailable($payment->payment_gateway)) {
            return $payment->payment_gateway;
        }

        // User's stored preference (if still enabled)
        if ($payment->user && $payment->user->preferred_payment_gateway
            && $this->gatewayAvailable($payment->user->preferred_payment_gateway)) {
            return $payment->user->preferred_payment_gateway;
        }

        // NGN: Paystack → Flutterwave → Klump (BNPL last-resort)
        if (\in_array($payment->currency, ['NGN', 'naira'], true)) {
            foreach (['paystack', 'flutterwave', 'klump'] as $gw) {
                if ($this->gatewayAvailable($gw)) {
                    return $gw;
                }
            }
        }

        // USD / international: DodoPayments → Stripe
        foreach (['dodopayments', 'stripe'] as $gw) {
            if ($this->gatewayAvailable($gw)) {
                return $gw;
            }
        }

        // Last resort: any enabled gateway
        foreach (['paystack', 'flutterwave', 'stripe', 'klump', 'dodopayments'] as $gw) {
            if ($this->gatewayAvailable($gw)) {
                return $gw;
            }
        }

        throw new Exception('No payment gateway is currently enabled. Please contact support.');
    }

    /**
     * Initialize payment with specific gateway
     *
     * @param  string  $gateway  Gateway name
     * @param  Payment  $payment  Payment model instance
     * @param  string  $reference  Payment reference
     * @param  array  $metadata  Additional metadata
     * @return array Initialization response
     *
     * @throws Exception
     */
    private function initializeWithGateway(string $gateway, Payment $payment, string $reference, array $metadata): array
    {
        // Handle anonymous payment data
        $isAnonymous = $metadata['is_anonymous'] ?? false;
        $customerEmail = $payment->user ? $payment->user->email : ($metadata['customer_email'] ?? null);
        $customerName = $metadata['customer_name'] ?? ($payment->user ? $payment->user->full_name : 'Customer');

        return match ($gateway) {
            'paystack' => $this->retryWithBackoff(function () use ($payment, $reference, $metadata, $customerEmail, $customerName) {
                return $this->paystackService->initializePayment(
                    $payment->amount,
                    $customerEmail,
                    $reference,
                    array_merge($metadata, [
                        'customer_name' => $customerName,
                        'title' => 'ONWYND Payment - ₦'.number_format($payment->amount),
                        'description' => $payment->description ?? 'Therapy session payment',
                    ])
                );
            }),
            'flutterwave' => $this->retryWithBackoff(function () use ($payment, $reference, $metadata, $customerEmail, $customerName) {
                return $this->flutterWaveService->initializePayment(
                    $payment->amount,
                    $customerEmail,
                    $reference,
                    array_merge($metadata, [
                        'customer_name' => $customerName,
                        'title' => 'ONWYND Payment - ₦'.number_format($payment->amount),
                        'description' => $payment->description ?? 'Therapy session payment',
                    ])
                );
            }),
            'stripe' => $this->retryWithBackoff(function () use ($payment, $reference, $metadata, $customerEmail) {
                return $this->stripeService->initializePayment(
                    $payment->amount,
                    $payment->currency,
                    $customerEmail,
                    $reference,
                    $metadata
                );
            }),
            'klump' => $this->retryWithBackoff(function () use ($payment, $reference, $metadata, $customerEmail, $customerName) {
                return $this->klumpService->initializePayment(
                    $payment->amount,
                    $customerEmail,
                    $reference,
                    array_merge($metadata, [
                        'customer_name' => $customerName,
                        'title' => 'ONWYND Payment - ₦'.number_format($payment->amount),
                        'description' => $payment->description ?? 'Therapy session payment',
                    ])
                );
            }),
            'dodopayments' => $this->retryWithBackoff(function () use ($payment, $reference, $metadata, $customerEmail, $customerName) {
                return $this->dodoPaymentsService->initializePayment(
                    $payment->amount,
                    $payment->currency ?? 'USD',
                    $customerEmail,
                    $reference,
                    array_merge($metadata, [
                        'customer_name' => $customerName,
                        'success_url' => $payment->metadata['success_url'] ?? null,
                        'description' => $payment->description ?? 'Payment',
                    ])
                );
            }),
            default => throw new Exception('Unsupported gateway: '.$gateway)
        };
    }

    /**
     * Get gateway service instance
     *
     * @param  string  $gateway  Gateway name
     * @return mixed Gateway service instance or null
     */
    private function getGatewayService(string $gateway)
    {
        return match ($gateway) {
            'paystack' => $this->paystackService,
            'flutterwave' => $this->flutterWaveService,
            'stripe' => $this->stripeService,
            'klump' => $this->klumpService,
            'dodopayments' => $this->dodoPaymentsService,
            default => null
        };
    }

    /**
     * Update payment from gateway verification
     *
     * @param  Payment  $payment  Payment to update
     * @param  array  $verificationData  Verification data from gateway
     */
    private function updatePaymentFromVerification(Payment $payment, array $verificationData): void
    {
        $updateData = [
            'gateway_payment_id' => $verificationData['transaction_id'] ?? $verificationData['id'] ?? null,
        ];

        if ($verificationData['success']) {
            $updateData['status'] = 'completed';
            $updateData['payment_status'] = 'paid';
            $updateData['completed_at'] = $verificationData['paid_at'] ?? now();
        } else {
            $updateData['status'] = 'failed';
            $updateData['payment_status'] = 'failed';
            $updateData['failed_at'] = now();
        }

        $payment->update($updateData);
    }

    /**
     * Process payout with specific gateway
     *
     * @param  string  $gateway  Gateway name
     * @param  string  $accountNumber  Bank account number
     * @param  string  $bankCode  Bank code
     * @param  float  $amount  Amount in Naira
     * @param  string  $reference  Payout reference
     * @param  string  $accountName  Account holder name
     * @return array Payout response
     */
    private function processPayoutWithGateway(string $gateway, string $accountNumber, string $bankCode, float $amount, string $reference, string $accountName): array
    {
        return match ($gateway) {
            'paystack' => $this->retryWithBackoff(function () use ($accountNumber, $bankCode, $amount, $reference, $accountName) {
                return $this->paystackService->createTransfer(
                    $accountNumber,
                    $bankCode,
                    $amount,
                    $reference,
                    $accountName
                );
            }),
            'flutterwave' => $this->retryWithBackoff(function () use ($accountNumber, $bankCode, $amount, $reference, $accountName) {
                return $this->flutterWaveService->createPayout(
                    $accountNumber,
                    $bankCode,
                    $amount,
                    $reference,
                    $accountName
                );
            }),
            default => throw new Exception('Unsupported gateway for payout: '.$gateway)
        };
    }

    /**
     * Generate unique payment reference
     *
     * @param  Payment  $payment  Payment model instance
     * @return string Unique reference
     */
    private function generatePaymentReference(Payment $payment): string
    {
        do {
            $reference = 'PAY-'.strtoupper(uniqid()).'-'.time();
        } while (Payment::where('payment_reference', $reference)->exists());

        return $reference;
    }

    /**
     * Generate unique payout reference
     *
     * @param  Therapist  $therapist  Therapist model instance
     * @return string Unique reference
     */
    private function generatePayoutReference(Therapist $therapist): string
    {
        do {
            $reference = 'PAYOUT-'.$therapist->id.'-'.strtoupper(uniqid()).'-'.time();
        } while (DB::table('payouts')->where('payment_reference', $reference)->exists());

        return $reference;
    }

    /**
     * Retry operation with exponential backoff
     *
     * @param  callable  $operation  Operation to retry
     * @return mixed Operation result
     *
     * @throws Exception
     */
    private function retryWithBackoff(callable $operation)
    {
        $lastException = null;

        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (Exception $e) {
                $lastException = $e;

                if ($attempt < $this->maxRetries - 1) {
                    $delay = (2 ** $attempt) * 1000; // Exponential backoff: 1s, 2s, 4s
                    usleep($delay * 1000);
                }
            }
        }

        throw $lastException ?? new Exception('Operation failed after retries');
    }
}
