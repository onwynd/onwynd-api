<?php

namespace Tests\Feature;

use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * ARCH-1: Tests for HasClinicalEthicsGuard — dual-role CA self-exclusion.
 *
 * A clinical_advisor who is also a treating therapist must NOT see their own
 * patients in distress flag counts or session review queries.
 */
class ClinicalEthicsGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'clinical_advisor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_coo_distress_count_excludes_ca_own_patients(): void
    {
        $ca = User::factory()->create();
        $ca->assignRole('clinical_advisor');

        $ownPatient = User::factory()->create();
        $otherPatient = User::factory()->create();

        // Create crisis events for both patients
        \App\Models\CrisisEvent::factory()->create([
            'user_id' => $ownPatient->id,
            'triggered_at' => now(),
        ]);
        \App\Models\CrisisEvent::factory()->create([
            'user_id' => $otherPatient->id,
            'triggered_at' => now(),
        ]);

        // Give CA a therapy session with own patient so getOwnPatientIds() returns ownPatient
        TherapySession::factory()->create([
            'therapist_id' => $ca->id,
            'patient_id' => $ownPatient->id,
        ]);

        $response = $this->actingAs($ca)
            ->getJson('/api/v1/coo/ai-operations');

        $response->assertStatus(200);
        $count = $response->json('data.crisis_performance.distress_flags_this_month');

        // CA's own patient should be excluded — only otherPatient counted
        $this->assertEquals(1, $count, 'CA own patient should be excluded from distress count');
    }

    public function test_non_ca_admin_sees_all_distress_flags(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $patients = User::factory()->count(3)->create();
        foreach ($patients as $patient) {
            \App\Models\CrisisEvent::factory()->create([
                'user_id' => $patient->id,
                'triggered_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/coo/ai-operations');

        $response->assertStatus(200);
        $count = $response->json('data.crisis_performance.distress_flags_this_month');

        $this->assertEquals(3, $count, 'Non-CA admin should see all distress flags');
    }
}
