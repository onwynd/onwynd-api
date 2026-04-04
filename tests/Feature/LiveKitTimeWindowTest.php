<?php

namespace Tests\Feature;

use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ARCH-4: Tests for LiveKit session join time window (T-5min to T+90min).
 *
 * Verifies patients are blocked outside the window;
 * therapists are always exempt.
 */
class LiveKitTimeWindowTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(User $therapist, User $patient, Carbon $scheduledAt): TherapySession
    {
        return TherapySession::factory()->create([
            'therapist_id' => $therapist->id,
            'patient_id' => $patient->id,
            'scheduled_at' => $scheduledAt,
            'status' => 'booked',
        ]);
    }

    public function test_patient_can_join_within_window(): void
    {
        $therapist = User::factory()->create();
        $patient = User::factory()->create();
        $session = $this->makeSession($therapist, $patient, now()->addMinutes(3));

        $response = $this->actingAs($patient)
            ->getJson("/api/v1/therapy/sessions/{$session->uuid}/join");

        $response->assertStatus(200);
    }

    public function test_patient_blocked_before_window_opens(): void
    {
        $therapist = User::factory()->create();
        $patient = User::factory()->create();
        $session = $this->makeSession($therapist, $patient, now()->addMinutes(30));

        $response = $this->actingAs($patient)
            ->getJson("/api/v1/therapy/sessions/{$session->uuid}/join");

        $response->assertStatus(403);
        $response->assertJsonPath('message', "Your session hasn't started yet.");
    }

    public function test_patient_blocked_after_window_closes(): void
    {
        $therapist = User::factory()->create();
        $patient = User::factory()->create();
        $session = $this->makeSession($therapist, $patient, now()->subMinutes(95));

        $response = $this->actingAs($patient)
            ->getJson("/api/v1/therapy/sessions/{$session->uuid}/join");

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'The join window for this session has closed.');
    }

    public function test_therapist_can_join_before_window_opens(): void
    {
        $therapist = User::factory()->create();
        $patient = User::factory()->create();
        $session = $this->makeSession($therapist, $patient, now()->addHours(2));

        $response = $this->actingAs($therapist)
            ->getJson("/api/v1/therapy/sessions/{$session->uuid}/join");

        // Therapist is exempt from the window check
        $response->assertStatus(200);
    }

    public function test_session_without_scheduled_time_allows_join(): void
    {
        $therapist = User::factory()->create();
        $patient = User::factory()->create();
        $session = TherapySession::factory()->create([
            'therapist_id' => $therapist->id,
            'patient_id' => $patient->id,
            'scheduled_at' => null,
            'status' => 'booked',
        ]);

        $response = $this->actingAs($patient)
            ->getJson("/api/v1/therapy/sessions/{$session->uuid}/join");

        $response->assertStatus(200);
    }
}
