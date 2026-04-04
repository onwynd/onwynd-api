<?php

namespace Tests\Feature;

use App\Jobs\ProcessMaintenanceNotifications;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles if they don't exist (RefreshDatabase wipes them)
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_tech_team_can_create_maintenance_request()
    {
        $techUser = User::factory()->create();
        $techUser->assignRole('tech_team');

        $response = $this->actingAs($techUser, 'sanctum')->postJson('/api/v1/tech/maintenance', [
            'title' => 'Database Upgrade',
            'description' => 'Upgrading to latest version',
            'start_time' => now()->addDay()->toIso8601String(),
            'end_time' => now()->addDay()->addHours(2)->toIso8601String(),
            'notify_users' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Database Upgrade')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('maintenance_schedules', [
            'title' => 'Database Upgrade',
            'requested_by' => $techUser->id,
        ]);
    }

    public function test_admin_can_approve_maintenance_request()
    {
        $techUser = User::factory()->create();
        $techUser->assignRole('tech_team');

        $adminUser = User::factory()->create();
        $adminUser->assignRole('admin');

        $schedule = MaintenanceSchedule::create([
            'title' => 'Pending Upgrade',
            'description' => 'Pending',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'pending',
            'requested_by' => $techUser->id,
            'notify_users' => false,
        ]);

        $response = $this->actingAs($adminUser, 'sanctum')
            ->postJson("/api/v1/admin/maintenance/{$schedule->id}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.approved_by', $adminUser->id);
    }

    public function test_system_status_endpoint_reflects_active_maintenance()
    {
        // Create an active maintenance schedule
        $techUser = User::factory()->create();
        $techUser->assignRole('tech_team');

        MaintenanceSchedule::create([
            'title' => 'Emergency Fix',
            'description' => 'Fixing critical bug',
            'start_time' => now()->subMinute(),
            'end_time' => now()->addHour(),
            'status' => 'in_progress', // Active status
            'requested_by' => $techUser->id,
            'approved_by' => $techUser->id, // Simulating approved
            'notify_users' => false,
        ]);

        $response = $this->getJson('/api/v1/system/status');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'maintenance')
            ->assertJsonPath('data.active_maintenance.title', 'Emergency Fix');
    }

    public function test_regular_user_cannot_create_maintenance()
    {
        $user = User::factory()->create();
        $user->assignRole('patient');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/tech/maintenance', [
            'title' => 'Hacked',
            'description' => 'Hacked',
            'start_time' => now()->addDay()->toIso8601String(),
            'end_time' => now()->addDay()->addHours(2)->toIso8601String(),
            'notify_users' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_maintenance_approval_dispatches_notification_job()
    {
        Queue::fake();

        $techUser = User::factory()->create();
        $techUser->assignRole('tech_team');

        $adminUser = User::factory()->create();
        $adminUser->assignRole('admin');

        $schedule = MaintenanceSchedule::create([
            'title' => 'Major Update',
            'description' => 'Update',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'pending',
            'requested_by' => $techUser->id,
            'notify_users' => true,
        ]);

        $response = $this->actingAs($adminUser, 'sanctum')
            ->postJson("/api/v1/admin/maintenance/{$schedule->id}/approve");

        $response->assertStatus(200);

        Queue::assertPushed(ProcessMaintenanceNotifications::class);
    }
}
