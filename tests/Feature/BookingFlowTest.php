<?php

namespace Tests\Feature;

use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $patient;
    protected User $therapist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->patient = User::factory()->create(['email' => 'patient@test.com']);
        $this->therapist = User::factory()->create(['email' => 'therapist@test.com']);

        \App\Models\Therapist::factory()->create([
            'user_id' => $this->therapist->id,
            'is_verified' => true,
            'is_accepting_clients' => true,
            'hourly_rate' => 8000,
            'status' => 'approved',
        ]);
    }

    /** @test */
    public function test_anonymous_quota_bypass_prevented(): void
    {
        $anonymousId = 'test-anon-session-id';

        // Seed an existing anonymous session with the same fingerprint
        TherapySession::factory()->create([
            'anonymous_fingerprint' => hash('sha256', '127.0.0.1' . 'test-agent' . $anonymousId),
            'is_anonymous' => true,
            'status' => 'scheduled',
            'created_at' => now()->subHour(),
        ]);

        $response = $this->postJson('/api/v1/sessions/book', [
            'therapist_id' => $this->therapist->id,
            'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'session_type' => 'video',
            'duration_minutes' => 60,
            'is_anonymous' => true,
            'anonymous_session_id' => $anonymousId,
        ], ['User-Agent' => 'test-agent']);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
        $this->assertStringContainsString(
            'anonymous',
            strtolower($response->json('message') ?? '')
        );
    }

    /** @test */
    public function test_double_booking_prevented(): void
    {
        $this->actingAs($this->patient);

        $scheduledAt = now()->addDays(3)->format('Y-m-d H:i:s');

        // Create a conflicting session at the same time slot
        TherapySession::factory()->create([
            'therapist_id' => $this->therapist->id,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => 60,
            'status' => 'scheduled',
        ]);

        $response = $this->postJson('/api/v1/sessions/book', [
            'therapist_id' => $this->therapist->id,
            'scheduled_at' => $scheduledAt,
            'session_type' => 'video',
            'duration_minutes' => 60,
        ]);

        // 422 Unprocessable or 409 Conflict — either signals a booking conflict
        $this->assertContains($response->status(), [409, 422]);
    }

    /** @test */
    public function test_cancellation_within_24_hours_rejected(): void
    {
        $this->actingAs($this->patient);

        $session = TherapySession::factory()->create([
            'patient_id' => $this->patient->id,
            'therapist_id' => $this->therapist->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addHours(12), // only 12 h away — within the 24 h window
        ]);

        $response = $this->postJson("/api/v1/patient/sessions/{$session->uuid}/cancel", [
            'reason' => 'Changed my mind',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('24', $response->json('message') ?? '');
    }
}
