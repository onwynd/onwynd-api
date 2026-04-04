<?php

namespace Tests\Feature\Payment;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Http::fake();
    }

    /**
     * Test payment initiation with valid data
     */
    public function test_can_initiate_payment(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/initiate', [
                'amount' => 5000,
                'currency' => 'NGN',
                'payment_type' => 'session_booking',
                'description' => 'Therapy session payment',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'payment_id',
                    'reference',
                    'authorization_url',
                    'amount',
                    'currency',
                    'gateway',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'amount' => 5000,
            'currency' => 'NGN',
            'payment_type' => 'session_booking',
            'status' => 'pending',
        ]);
    }

    /**
     * Test payment initiation with invalid amount (too low)
     */
    public function test_cannot_initiate_payment_with_amount_below_minimum(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/initiate', [
                'amount' => 50, // Below minimum of 100
                'currency' => 'NGN',
                'payment_type' => 'session_booking',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test payment initiation with invalid amount (too high)
     */
    public function test_cannot_initiate_payment_with_amount_above_maximum(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/initiate', [
                'amount' => 101000000, // Above maximum
                'currency' => 'NGN',
                'payment_type' => 'session_booking',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test payment initiation without authentication
     */
    public function test_cannot_initiate_payment_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/payments/initiate', [
            'amount' => 5000,
            'currency' => 'NGN',
            'payment_type' => 'session_booking',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test payment initiation with missing required fields
     */
    public function test_cannot_initiate_payment_without_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/initiate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'payment_type']);
    }

    /**
     * Test payment verification
     */
    public function test_can_verify_payment(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/payments/{$payment->id}/verify", []);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'payment_id',
                    'status',
                    'amount',
                    'currency',
                    'gateway',
                    'verified_at',
                    'is_paid',
                ],
            ]);
    }

    /**
     * Test cannot verify payment belonging to another user
     */
    public function test_cannot_verify_payment_of_other_user(): void
    {
        $otherUser = User::factory()->create();
        $payment = Payment::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/payments/{$payment->id}/verify", []);

        $response->assertStatus(403);
    }

    /**
     * Test get payment history
     */
    public function test_can_get_payment_history(): void
    {
        Payment::factory()->count(5)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payments?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'payments' => [
                        '*' => [
                            'id',
                            'amount',
                            'currency',
                            'status',
                            'payment_type',
                            'gateway',
                            'reference',
                            'created_at',
                            'completed_at',
                        ],
                    ],
                    'pagination',
                ],
            ]);

        $this->assertEquals(5, $response->json('data.pagination.total'));
    }

    /**
     * Test get payment history with status filter
     */
    public function test_can_filter_payment_history_by_status(): void
    {
        Payment::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
        ]);
        Payment::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payments?status=completed');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('data.pagination.total'));
    }

    /**
     * Test get single payment
     */
    public function test_can_get_single_payment(): void
    {
        $payment = Payment::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'amount',
                    'currency',
                    'status',
                    'payment_type',
                    'gateway',
                    'reference',
                    'metadata',
                    'created_at',
                    'initiated_at',
                    'completed_at',
                    'failed_at',
                    'refunds',
                ],
            ]);
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
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/payments/{$payment->id}/refund", [
                'amount' => 2500,
                'reason' => 'customer_request',
                'notes' => 'Customer requested partial refund',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'payment_id',
                    'refund_amount',
                    'remaining_amount',
                    'status',
                    'processed_at',
                ],
            ]);
    }

    /**
     * Test cannot refund with amount exceeding payment
     */
    public function test_cannot_refund_more_than_payment_amount(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 5000,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/payments/{$payment->id}/refund", [
                'amount' => 10000, // More than payment amount
            ]);

        $response->assertStatus(400);
    }

    /**
     * Test get payment summary
     */
    public function test_can_get_payment_summary(): void
    {
        Payment::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 5000,
            'status' => 'completed',
        ]);
        Payment::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 3000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payments/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_payments',
                    'total_amount_paid',
                    'pending_amount',
                    'failed_payments',
                    'refunded_amount',
                    'currency',
                ],
            ]);

        $this->assertEquals(5000, $response->json('data.total_amount_paid'));
        $this->assertEquals(3000, $response->json('data.pending_amount'));
    }

    /**
     * Test cannot refund uncompleted payment
     */
    public function test_cannot_refund_uncompleted_payment(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/payments/{$payment->id}/refund", [
                'amount' => 1000,
            ]);

        $response->assertStatus(400);
    }
}
