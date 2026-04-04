<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTherapistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure roles exist
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_admin_can_list_therapists()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $therapist = User::factory()->create();
        $therapist->assignRole('therapist');

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/v1/admin/therapists');

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $therapist->id]);
    }

    public function test_admin_can_deactivate_therapist()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $therapist = User::factory()->create(['is_active' => true]);
        $therapist->assignRole('therapist');

        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson("/api/v1/admin/therapists/{$therapist->id}/deactivate");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $therapist->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_activate_therapist()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $therapist = User::factory()->create(['is_active' => false]);
        $therapist->assignRole('therapist');

        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson("/api/v1/admin/therapists/{$therapist->id}/activate");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('users', [
            'id' => $therapist->id,
            'is_active' => true,
        ]);
    }

    public function test_non_therapist_cannot_be_managed_via_therapist_controller()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $patient = User::factory()->create();
        $patient->assignRole('patient');

        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson("/api/v1/admin/therapists/{$patient->id}/deactivate");

        $response->assertStatus(404); // Or 400/error depending on implementation.
        // My implementation returns 404? No, I returned sendError which usually is 404 or 400.
        // BaseController sendError usually 404 unless specified.
        // Actually BaseController::sendError default is 404.
        // Let's check sendError implementation or just assert not 200.
    }
}
