<?php

namespace Tests\Feature\Payment;

use App\Models\User;
use App\Services\PaymentService\PaymentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fakes\FakePaymentProcessor;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->singleton(PaymentProcessor::class, function () {
            return new FakePaymentProcessor;
        });
    }

    public function test_can_initialize_and_verify_payment_via_alias()
    {
        config(['queue.default' => 'sync']);
        config(['mail.default' => 'array']);

        $user = User::factory()->create([
            'email' => 'user'.Str::random(6).'@example.com',
            'password' => 'password',
        ]);
        Sanctum::actingAs($user);

        $init = $this->postJson('/api/v1/payments/initialize', [
            'amount' => 5000,
            'payment_type' => 'subscription',
        ]);

        $init->assertStatus(201);
        $ref = $init->json('data.reference');

        $verify = $this->postJson('/api/v1/payments/verify', [
            'reference' => $ref,
        ]);

        $verify->assertStatus(200);
        $verify->assertJsonPath('data.status', 'completed');
    }
}
