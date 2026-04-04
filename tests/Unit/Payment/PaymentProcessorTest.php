<?php

namespace Tests\Unit\Payment;

use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService\PaymentProcessor;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected $processor;

    protected $user;

    protected $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new PaymentProcessor;
        $this->user = User::factory()->create();
        Http::fake();
    }

    /**
     * Test payment initialization
     */
    public function test_can_process_payment(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 5000,
            'currency' => 'NGN',
        ]);

        $result = $this->processor->processPayment($payment);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('authorization_url', $result);
        $this->assertArrayHasKey('reference', $result);
        $this->assertArrayHasKey('gateway', $result);
    }

    /**
     * Test payment processing with invalid amount
     */
    public function test_cannot_process_payment_with_invalid_amount(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 0,
        ]);

        $this->expectException(Exception::class);
        $this->processor->processPayment($payment);
    }

    /**
     * Test payment verification
     */
    public function test_can_verify_payment(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'payment_gateway' => 'paystack',
            'payment_reference' => 'TEST-REF-123',
            'status' => 'pending',
        ]);

        $result = $this->processor->verifyPayment($payment);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * Test cannot verify payment without gateway info
     */
    public function test_cannot_verify_payment_without_gateway_info(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'payment_gateway' => null,
            'payment_reference' => null,
        ]);

        $this->expectException(Exception::class);
        $this->processor->verifyPayment($payment);
    }

    /**
     * Test refund payment
     */
    public function test_can_refund_payment(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 5000,
            'status' => 'completed',
            'payment_gateway' => 'paystack',
            'gateway_payment_id' => 'TEST-TXN-123',
        ]);

        $result = $this->processor->refundPayment($payment, 2500);

        $this->assertTrue($result['success']);
        $this->assertEquals(2500, $result['refund_amount']);
    }

    /**
     * Test cannot refund pending payment
     */
    public function test_cannot_refund_pending_payment(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $this->expectException(Exception::class);
        $this->processor->refundPayment($payment);
    }

    /**
     * Test refund with amount exceeding payment
     */
    public function test_cannot_refund_amount_exceeding_payment(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 5000,
            'status' => 'completed',
        ]);

        $this->expectException(Exception::class);
        $this->processor->refundPayment($payment, 10000);
    }

    /**
     * Test get transaction status
     */
    public function test_can_get_transaction_status(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
        ]);

        $result = $this->processor->getTransactionStatus($payment);

        $this->assertArrayHasKey('payment_id', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertEquals($payment->id, $result['payment_id']);
    }

    /**
     * Test webhook handling
     */
    public function test_can_handle_webhook(): void
    {
        $data = [
            'event' => 'charge.success',
            'data' => [
                'id' => 'TXN-123',
                'amount' => 5000,
                'status' => 'success',
            ],
        ];

        $result = $this->processor->handleWebhook('paystack', $data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * Test reconciliation
     */
    public function test_can_reconcile_payments(): void
    {
        Payment::factory()->count(5)->create([
            'payment_gateway' => 'paystack',
            'status' => 'completed',
        ]);

        $result = $this->processor->reconcilePayments(
            'paystack',
            now()->subDays(7),
            now()
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('reconciled', $result);
    }
}
