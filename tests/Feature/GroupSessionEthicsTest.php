<?php

namespace Tests\Feature;

use App\Models\GroupSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CQ-8: Tests for GroupSessionController CA self-exclusion on end().
 *
 * Verifies that a clinical_advisor cannot end a group session where
 * they are the assigned treating therapist.
 */
class GroupSessionEthicsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'clinical_advisor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'therapist', 'guard_name' => 'web']);
    }

    public function test_therapist_can_end_their_own_session(): void
    {
        $therapist = User::factory()->create();
        $therapist->assignRole('therapist');

        $session = GroupSession::factory()->create([
            'therapist_id' => $therapist->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($therapist)
            ->postJson("/api/v1/group-sessions/{$session->uuid}/end");

        $response->assertStatus(200);
    }

    public function test_ca_can_end_session_where_they_are_not_therapist(): void
    {
        $ca = User::factory()->create();
        $ca->assignRole('clinical_advisor');

        $therapist = User::factory()->create();

        $session = GroupSession::factory()->create([
            'therapist_id' => $therapist->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($ca)
            ->postJson("/api/v1/group-sessions/{$session->uuid}/end");

        $response->assertStatus(200);
    }

    public function test_ca_cannot_end_session_where_they_are_the_therapist(): void
    {
        $ca = User::factory()->create();
        $ca->assignRole('clinical_advisor');

        // CA is also the treating therapist — this violates self-exclusion rule
        $session = GroupSession::factory()->create([
            'therapist_id' => $ca->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($ca)
            ->postJson("/api/v1/group-sessions/{$session->uuid}/end");

        // CA as own therapist = unauthorized
        $response->assertStatus(403);
    }

    public function test_unrelated_user_cannot_end_session(): void
    {
        $therapist = User::factory()->create();
        $other = User::factory()->create();

        $session = GroupSession::factory()->create([
            'therapist_id' => $therapist->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($other)
            ->postJson("/api/v1/group-sessions/{$session->uuid}/end");

        $response->assertStatus(403);
    }
}
