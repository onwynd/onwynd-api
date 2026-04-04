<?php

namespace Tests\Feature\API\Therapist;

use App\Models\Review;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TherapistProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $therapistUser;

    protected Therapist $therapist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->therapistUser = User::factory()->create(['role' => 'therapist']);
        $this->therapist = Therapist::factory()->create(['user_id' => $this->therapistUser->id]);
    }

    /**
     * Test getting therapist profile
     */
    public function test_get_profile()
    {
        $response = $this->actingAs($this->therapistUser)
            ->getJson('/api/v1/therapist/profile');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'full_name',
                'email',
                'specialization',
                'stats',
            ],
        ]);
    }

    /**
     * Test non-therapist cannot access therapist profile
     */
    public function test_non_therapist_cannot_access()
    {
        $patientUser = User::factory()->create(['role' => 'patient']);

        $response = $this->actingAs($patientUser)
            ->getJson('/api/v1/therapist/profile');

        $response->assertStatus(404);
    }

    /**
     * Test updating therapist profile
     */
    public function test_update_profile()
    {
        $response = $this->actingAs($this->therapistUser)
            ->putJson('/api/v1/therapist/profile', [
                'full_name' => 'Dr. John Updated',
                'bio' => 'Updated bio',
                'specialization' => 'Cognitive Behavioral Therapy',
                'years_of_experience' => 10,
                'hourly_rate' => 25000,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('therapists', [
            'id' => $this->therapist->id,
            'full_name' => 'Dr. John Updated',
            'specialization' => 'Cognitive Behavioral Therapy',
        ]);
    }

    /**
     * Test getting therapist availability
     */
    public function test_get_availability()
    {
        $response = $this->actingAs($this->therapistUser)
            ->getJson('/api/v1/therapist/availability');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'therapist_id',
                'schedule',
                'timezone',
            ],
        ]);
    }

    /**
     * Test updating therapist availability
     */
    public function test_update_availability()
    {
        $response = $this->actingAs($this->therapistUser)
            ->putJson('/api/v1/therapist/availability', [
                'day_of_week' => 1, // Monday
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_available' => true,
            ]);

        $response->assertStatus(200);
    }

    /**
     * Test getting therapist reviews
     */
    public function test_get_reviews()
    {
        Review::factory()->count(5)->create(['therapist_id' => $this->therapist->id]);

        $response = $this->actingAs($this->therapistUser)
            ->getJson('/api/v1/therapist/reviews');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'average_rating',
                'total_reviews',
                'rating_distribution',
                'reviews',
                'pagination',
            ],
        ]);
    }

    /**
     * Test updating bank details
     */
    public function test_update_bank_details()
    {
        $response = $this->actingAs($this->therapistUser)
            ->putJson('/api/v1/therapist/bank-details', [
                'account_holder' => 'Dr. John Doe',
                'account_number' => '1234567890',
                'bank_code' => '044',
                'bank_name' => 'Guaranty Trust Bank',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'account_holder',
                'account_number',
                'bank_code',
                'bank_name',
            ],
        ]);
    }

    /**
     * Test invalid bank account number
     */
    public function test_invalid_bank_account_number()
    {
        $response = $this->actingAs($this->therapistUser)
            ->putJson('/api/v1/therapist/bank-details', [
                'account_holder' => 'Dr. John Doe',
                'account_number' => '123', // Invalid - should be 10 digits
                'bank_code' => '044',
                'bank_name' => 'GTB',
            ]);

        $response->assertStatus(400);
    }

    /**
     * Test getting earnings summary
     */
    public function test_get_earnings()
    {
        TherapySession::factory()->count(5)->create([
            'therapist_id' => $this->therapist->id,
            'status' => 'completed',
            'session_fee' => 10000,
        ]);

        $response = $this->actingAs($this->therapistUser)
            ->getJson('/api/v1/therapist/earnings');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'period',
                'total_earnings',
                'total_payouts',
                'pending_balance',
                'completed_sessions',
            ],
        ]);
    }

    /**
     * Test getting earnings for specific period
     */
    public function test_get_earnings_for_period()
    {
        TherapySession::factory()->count(3)->create([
            'therapist_id' => $this->therapist->id,
            'status' => 'completed',
            'session_fee' => 10000,
        ]);

        $response = $this->actingAs($this->therapistUser)
            ->getJson('/api/v1/therapist/earnings?period=year');

        $response->assertStatus(200);
    }

    /**
     * Test getting therapist schedule
     */
    public function test_get_schedule()
    {
        TherapySession::factory()->count(3)->create([
            'therapist_id' => $this->therapist->id,
            'status' => 'booked',
        ]);

        $response = $this->actingAs($this->therapistUser)
            ->getJson('/api/v1/therapist/schedule');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'view',
                'start_date',
                'end_date',
                'sessions',
            ],
        ]);
    }

    /**
     * Test updating hourly rate
     */
    public function test_update_hourly_rate()
    {
        $response = $this->actingAs($this->therapistUser)
            ->putJson('/api/v1/therapist/profile', [
                'hourly_rate' => 30000,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('therapists', [
            'id' => $this->therapist->id,
            'hourly_rate' => 30000,
        ]);
    }

    /**
     * Test invalid hourly rate
     */
    public function test_invalid_hourly_rate()
    {
        $response = $this->actingAs($this->therapistUser)
            ->putJson('/api/v1/therapist/profile', [
                'hourly_rate' => 500, // Below minimum
            ]);

        $response->assertStatus(400);
    }

    /**
     * Test getting profile with stats
     */
    public function test_get_profile_with_stats()
    {
        TherapySession::factory()->count(10)->create([
            'therapist_id' => $this->therapist->id,
            'status' => 'completed',
        ]);

        Review::factory()->count(3)->create([
            'therapist_id' => $this->therapist->id,
            'rating' => 5,
        ]);

        $response = $this->actingAs($this->therapistUser)
            ->getJson('/api/v1/therapist/profile');

        $response->assertStatus(200);
        $response->assertJsonPath('data.stats.total_sessions', 10);
        $response->assertJsonPath('data.stats.completed_sessions', 10);
    }

    /**
     * Test updating languages
     */
    public function test_update_languages()
    {
        $response = $this->actingAs($this->therapistUser)
            ->putJson('/api/v1/therapist/profile', [
                'languages' => ['English', 'French', 'Spanish'],
            ]);

        $response->assertStatus(200);
    }

    /**
     * Test unauthenticated access
     */
    public function test_unauthenticated_access()
    {
        $response = $this->getJson('/api/v1/therapist/profile');

        $response->assertStatus(401);
    }
}
