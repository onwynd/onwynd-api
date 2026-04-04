<?php

namespace Tests\Support\Fakes;

use App\Models\Payment;

class FakePaymentProcessor
{
    public function processPayment(Payment $payment, array $metadata = []): array
    {
        $reference = 'FAKE_REF_'.uniqid();
        $payment->update([
            'payment_gateway' => 'paystack',
            'payment_reference' => $reference,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        return [
            'success' => true,
            'authorization_url' => 'https://example.com/pay/'.$reference,
            'reference' => $reference,
            'gateway' => 'paystack',
        ];
    }

    public function verifyPayment(Payment $payment): array
    {
        $payment->update([
            'status' => 'completed',
            'payment_status' => 'completed',
        ]);

        return [
            'success' => true,
            'status' => 'completed',
        ];
    }

    public function refundPayment(Payment $payment, ?float $amount = null): array
    {
        return [
            'success' => true,
            'refund_amount' => $amount ?? $payment->amount,
        ];
    }
}
