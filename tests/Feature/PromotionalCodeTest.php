<?php

namespace Tests\Feature;

use App\Models\PromotionalCode;
use App\Models\PromotionalCodeUsage;
use App\Models\User;
use App\Services\PromotionalCodeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionalCodeTest extends TestCase
{
    use RefreshDatabase;

    private PromotionalCodeService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PromotionalCodeService();
        $this->user    = User::factory()->create();
    }

    /** @test */
    public function test_valid_code_applies_discount(): void
    {
        PromotionalCode::factory()->create([
            'code'           => 'SAVE20',
            'type'           => 'percentage',
            'discount_value' => 20,
            'currency'       => 'NGN',
            'is_active'      => true,
            'uses_count'     => 0,
        ]);

        $result = $this->service->validate('SAVE20', $this->user->id, 'NGN', 10000);

        $this->assertTrue($result['valid']);
        // 20 % of 10,000 = 2,000
        $this->assertEquals(2000.0, $result['discount_amount']);
    }

    /** @test */
    public function test_expired_code_rejected(): void
    {
        PromotionalCode::factory()->create([
            'code'        => 'OLD2025',
            'type'        => 'fixed',
            'discount_value' => 500,
            'is_active'   => true,
            'valid_until' => Carbon::now()->subDay(),
        ]);

        $result = $this->service->validate('OLD2025', $this->user->id, 'NGN', 5000);

        $this->assertFalse($result['valid']);
        // Service message: "This code has expired."
        $this->assertStringContainsString('expired', strtolower($result['message']));
    }

    /** @test */
    public function test_max_uses_code_rejected_after_limit(): void
    {
        PromotionalCode::factory()->create([
            'code'           => 'LIMITED',
            'type'           => 'fixed',
            'discount_value' => 500,
            'is_active'      => true,
            'max_uses'       => 10,
            'uses_count'     => 10,
        ]);

        $result = $this->service->validate('LIMITED', $this->user->id, 'NGN', 5000);

        $this->assertFalse($result['valid']);
        // Service message: "This code has reached its maximum number of uses."
        $this->assertStringContainsString('maximum', strtolower($result['message']));
    }

    /** @test */
    public function test_currency_mismatch_rejected(): void
    {
        PromotionalCode::factory()->create([
            'code'           => 'USDONLY',
            'type'           => 'fixed',
            'discount_value' => 10,
            'currency'       => 'USD',
            'is_active'      => true,
        ]);

        $result = $this->service->validate('USDONLY', $this->user->id, 'NGN', 5000);

        $this->assertFalse($result['valid']);
        // Service message: "This code is not valid for your currency."
        $this->assertStringContainsString('currency', strtolower($result['message']));
    }

    /** @test */
    public function test_per_user_limit_enforced(): void
    {
        $code = PromotionalCode::factory()->create([
            'code'              => 'ONETIME',
            'type'              => 'fixed',
            'discount_value'    => 500,
            'is_active'         => true,
            'max_uses_per_user' => 1,
        ]);

        // Record that the user already used this code once
        PromotionalCodeUsage::factory()->create([
            'promotional_code_id' => $code->id,
            'user_id'             => $this->user->id,
            'discount_applied'    => 500,
        ]);

        $result = $this->service->validate('ONETIME', $this->user->id, 'NGN', 5000);

        $this->assertFalse($result['valid']);
        // Service message: "You have already used this code the maximum number of times."
        $this->assertStringContainsString('already used', strtolower($result['message']));
    }
}
