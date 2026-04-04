<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for introductory session pricing on therapist profiles.
 *
 * Therapist::getEffectiveRateForUser(?int $userId) is the core method under test.
 *
 * Public therapist endpoint: GET /api/v1/therapists/{id}
 *   - Returns user with nested therapistProfile
 *   - The response includes therapist_profile.introductory_rate_active
 */
class IntroductoryRateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper: create a therapist user + profile.
     */
    private function makeTherapist(array $profileOverrides = []): array
    {
        $therapistRole = Role::factory()->create(['slug' => 'therapist', 'name' => 'Therapist']);
        $therapistUser = User::factory()->create(['role_id' => $therapistRole->id]);

        $defaults = [
            'user_id'              => $therapistUser->id,
            'is_verified'          => true,
            'is_accepting_clients' => true,
            'status'               => 'approved',
            'hourly_rate'          => 15000,
            'payout_currency'      => 'NGN',
        ];

        $profile = Therapist::factory()->create(array_merge($defaults, $profileOverrides));

        return [$therapistUser, $profile];
    }

    /**
     * When introductory_rate_active = true and the patient has zero completed sessions,
     * getEffectiveRateForUser() should return the introductory rate.
     */
    public function test_introductory_rate_shown_to_users(): void
    {
        [$therapistUser, $profile] = $this->makeTherapist([
            'introductory_rate'          => 5000,
            'introductory_sessions_count' => 5,
            'introductory_rate_active'   => true,
            'hourly_rate'                => 15000,
        ]);

        $patientRole = Role::factory()->create(['slug' => 'patient', 'name' => 'Patient']);
        $patient     = User::factory()->create(['role_id' => $patientRole->id]);

        $result = $profile->getEffectiveRateForUser($patient->id);

        $this->assertEquals(5000.0, $result['rate']);
        $this->assertTrue($result['is_introductory']);
        $this->assertEquals(5, $result['sessions_remaining']);
        $this->assertEquals('NGN', $result['currency']);
    }

    /**
     * After the patient has exhausted the introductory session allotment,
     * getEffectiveRateForUser() should return the standard hourly_rate.
     */
    public function test_rate_auto_reverts_after_session_limit(): void
    {
        [$therapistUser, $profile] = $this->makeTherapist([
            'introductory_rate'           => 5000,
            'introductory_sessions_count' => 3,
            'introductory_rate_active'    => true,
            'hourly_rate'                 => 15000,
        ]);

        $patientRole = Role::factory()->create(['slug' => 'patient', 'name' => 'Patient']);
        $patient     = User::factory()->create(['role_id' => $patientRole->id]);

        // Create exactly 3 completed sessions for this patient with this therapist
        TherapySession::factory()->count(3)->create([
            'patient_id'   => $patient->id,
            'therapist_id' => $therapistUser->id,
            'status'       => 'completed',
        ]);

        $result = $profile->getEffectiveRateForUser($patient->id);

        $this->assertEquals(15000.0, $result['rate'], 'Standard rate must apply once introductory allotment is exhausted');
        $this->assertFalse($result['is_introductory']);
    }

    /**
     * When introductory_rate_active = false, the standard session_rate is always returned
     * regardless of session count.
     */
    public function test_standard_rate_shown_after_intro_period(): void
    {
        [$therapistUser, $profile] = $this->makeTherapist([
            'introductory_rate_active' => false,
            'hourly_rate'              => 15000,
            'introductory_rate'        => 5000,
        ]);

        $result = $profile->getEffectiveRateForUser(null);

        $this->assertEquals(15000.0, $result['rate']);
        $this->assertFalse($result['is_introductory']);
    }

    /**
     * The public therapist show endpoint exposes introductory_rate_active in the
     * therapistProfile JSON so the frontend can conditionally render the badge.
     *
     * GET /api/v1/therapists/{id} (no auth required — public route)
     */
    public function test_badge_disappears_after_intro_period(): void
    {
        [$therapistUser, $profile] = $this->makeTherapist([
            'introductory_rate_active' => false,
            'hourly_rate'              => 15000,
        ]);

        $response = $this->getJson("/api/v1/therapists/{$therapistUser->id}");

        $response->assertOk();

        // The endpoint returns the User with nested therapist_profile
        $therapistProfileData = $response->json('data.therapist_profile');
        $this->assertNotNull($therapistProfileData, 'therapist_profile must be present in the response');
        $this->assertFalse(
            (bool) ($therapistProfileData['introductory_rate_active'] ?? true),
            'introductory_rate_active must be false in the API response'
        );
    }
}
