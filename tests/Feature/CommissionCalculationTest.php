<?php

namespace Tests\Feature;

use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\TherapistCompensationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for TherapistCompensationService::calculateCommission().
 *
 * NOTE: calculateCommission() returns the amount the THERAPIST KEEPS
 * (session_rate * therapist_keep_percent / 100), not the platform's cut.
 *
 * Tier structure seeded in setUp() for both NGN and USD:
 *   NGN: ≤5 000 → 90 % | 5 001–10 000 → 85 % | 10 001–20 000 → 82 % | >20 000 → 80 %
 *   USD: ≤35   → 90 % | 36–70        → 85 % | 71–140         → 82 % | >140    → 80 %
 */
class CommissionCalculationTest extends TestCase
{
    use RefreshDatabase;

    private TherapistCompensationService $service;
    private Therapist $therapist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TherapistCompensationService();

        $user = User::factory()->create();
        $this->therapist = Therapist::factory()->create([
            'user_id'    => $user->id,
            'is_founding' => false,
        ]);

        // Seed commission tiers for NGN and USD
        \App\Models\Setting::updateOrCreate(
            ['group' => 'commission', 'key' => 'tiers'],
            [
                'value' => json_encode([
                    'NGN' => [
                        ['min' => 1,     'max' => 5000,  'therapist_keep_percent' => 90],
                        ['min' => 5001,  'max' => 10000, 'therapist_keep_percent' => 85],
                        ['min' => 10001, 'max' => 20000, 'therapist_keep_percent' => 82],
                        ['min' => 20001, 'max' => null,  'therapist_keep_percent' => 80],
                    ],
                    'USD' => [
                        ['min' => 1,   'max' => 35,  'therapist_keep_percent' => 90],
                        ['min' => 36,  'max' => 70,  'therapist_keep_percent' => 85],
                        ['min' => 71,  'max' => 140, 'therapist_keep_percent' => 82],
                        ['min' => 141, 'max' => null, 'therapist_keep_percent' => 80],
                    ],
                ]),
                'type' => 'json',
            ]
        );
    }

    /** @test */
    public function test_ngn_tier_1_commission_correct(): void
    {
        // N4,000 is in tier 1 (≤5,000) → therapist keeps 90 % → 3,600
        $session = TherapySession::factory()->create([
            'session_rate'   => 4000,
            'therapist_id'   => $this->therapist->user_id,
            'payment_method' => 'paystack',
        ]);

        $therapistKeep = $this->service->calculateCommission($session);

        $this->assertEquals(3600.0, $therapistKeep);
    }

    /** @test */
    public function test_ngn_tier_2_commission_correct(): void
    {
        // N8,000 is in tier 2 (5,001–10,000) → therapist keeps 85 % → 6,800
        $session = TherapySession::factory()->create([
            'session_rate'   => 8000,
            'therapist_id'   => $this->therapist->user_id,
            'payment_method' => 'paystack',
        ]);

        $therapistKeep = $this->service->calculateCommission($session);

        $this->assertEquals(6800.0, $therapistKeep);
    }

    /** @test */
    public function test_ngn_tier_3_commission_correct(): void
    {
        // N15,000 is in tier 3 (10,001–20,000) → therapist keeps 82 % → 12,300
        $session = TherapySession::factory()->create([
            'session_rate'   => 15000,
            'therapist_id'   => $this->therapist->user_id,
            'payment_method' => 'paystack',
        ]);

        $therapistKeep = $this->service->calculateCommission($session);

        $this->assertEquals(12300.0, $therapistKeep);
    }

    /** @test */
    public function test_ngn_tier_4_commission_correct(): void
    {
        // N25,000 is in tier 4 (>20,000) → therapist keeps 80 % → 20,000
        $session = TherapySession::factory()->create([
            'session_rate'   => 25000,
            'therapist_id'   => $this->therapist->user_id,
            'payment_method' => 'paystack',
        ]);

        $therapistKeep = $this->service->calculateCommission($session);

        $this->assertEquals(20000.0, $therapistKeep);
    }

    /** @test */
    public function test_founding_bonus_applied(): void
    {
        // Founding therapist gets +3 % keep on top of the standard tier rate.
        // N8,000 → standard 85 % + 3 % founding = 88 % → therapist keeps 7,040
        $this->therapist->update([
            'is_founding'         => true,
            'founding_started_at' => now()->subMonths(6),
        ]);

        \App\Models\Setting::updateOrCreate(
            ['group' => 'commission', 'key' => 'founding_discount_percent'],
            ['value' => '3', 'type' => 'string']
        );

        $session = TherapySession::factory()->create([
            'session_rate'   => 8000,
            'therapist_id'   => $this->therapist->user_id,
            'payment_method' => 'paystack',
        ]);

        $therapistKeep = $this->service->calculateCommission($session);

        // 88 % of 8,000 = 7,040
        $this->assertEquals(7040.0, $therapistKeep);
    }

    /** @test */
    public function test_usd_tier_1_commission_correct(): void
    {
        // $25 is in USD tier 1 (≤35) → therapist keeps 90 % → $22.50
        $session = TherapySession::factory()->create([
            'session_rate'   => 25,
            'therapist_id'   => $this->therapist->user_id,
            'payment_method' => 'stripe',
            'currency'       => 'USD',
        ]);

        $therapistKeep = $this->service->calculateCommission($session);

        $this->assertEquals(22.5, $therapistKeep);
    }

    /** @test */
    public function test_usd_tier_2_commission_correct(): void
    {
        // $50 is in USD tier 2 (36–70) → therapist keeps 85 % → $42.50
        $session = TherapySession::factory()->create([
            'session_rate'   => 50,
            'therapist_id'   => $this->therapist->user_id,
            'payment_method' => 'stripe',
            'currency'       => 'USD',
        ]);

        $therapistKeep = $this->service->calculateCommission($session);

        $this->assertEquals(42.5, $therapistKeep);
    }
}
