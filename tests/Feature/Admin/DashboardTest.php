<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create admin role if not exists
        if (! Role::where('name', 'admin')->exists()) {
            Role::create(['name' => 'admin']);
        }
    }

    public function test_admin_can_access_dashboard_stats()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'users',
                    'revenue',
                    'system_health',
                ],
                'message',
            ]);
    }

    public function test_non_admin_cannot_access_dashboard_stats()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(403);
    }
}
