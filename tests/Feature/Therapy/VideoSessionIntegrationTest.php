<?php

namespace Tests\Feature\Therapy;

use App\Models\Role;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideoSessionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $therapist;

    protected $patient;

    protected $therapySession;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure LiveKit for tests
        putenv('LIVEKIT_API_KEY=testing');
        putenv('LIVEKIT_API_SECRET=testing-secret');
        putenv('LIVEKIT_HOST=https://livekit.test');

        // Create Roles
        Role::create(['id' => 1, 'name' => 'Admin', 'slug' => 'admin', 'permissions' => []]);
        Role::create(['id' => 2, 'name' => 'Therapist', 'slug' => 'therapist', 'permissions' => []]);
        Role::create(['id' => 3, 'name' => 'Patient', 'slug' => 'patient', 'permissions' => []]);

        $this->therapist = User::factory()->create(['role_id' => 2]);
        $this->patient = User::factory()->create(['role_id' => 3]);

        $this->therapySession = TherapySession::factory()->create([
            'therapist_id' => $this->therapist->id,
            'patient_id' => $this->patient->id,
            'status' => 'scheduled',
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }

    public function test_join_returns_livekit_payload_for_patient()
    {
        Sanctum::actingAs($this->patient);
        $response = $this->postJson("/api/v1/sessions/{$this->therapySession->uuid}/video/join");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'url',
                    'room_name',
                    'ice_servers',
                    'participant' => ['identity', 'name', 'role'],
                    'session' => ['id', 'scheduled_at', 'duration', 'status'],
                ],
            ])
            ->assertJsonPath('data.participant.role', 'subscriber');
    }

    public function test_token_endpoint_issues_livekit_token_for_therapist()
    {
        Sanctum::actingAs($this->therapist);
        $response = $this->postJson('/api/v1/therapy/video/token', [
            'session_id' => $this->therapySession->id,
            'room' => 'session-'.$this->therapySession->uuid,
            'role' => 'publisher',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['token', 'host', 'room'],
            ]);
    }
}
