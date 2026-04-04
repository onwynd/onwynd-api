<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeatureToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Settings are already seeded by migration
    }

    public function test_admin_can_update_feature_settings()
    {
        $admin = User::factory()->create();
        // Assume logic to give admin role, or mock permission
        // Since I don't know the exact Role implementation details (Spatie or custom),
        // I'll try to rely on the middleware skipping or mocking if possible,
        // but AdminSettingsController uses 'role:admin'.
        // I will create a role for admin.

        $role = \App\Models\Role::create([
            'name' => 'admin',
            'slug' => 'admin',
            'permissions' => [],
        ]);
        $admin->role_id = $role->id;
        $admin->save();

        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/v1/admin/settings', [
            'settings' => [
                [
                    'key' => 'feature_eprescriptions_enabled',
                    'value' => 'false',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals('false', Setting::where('key', 'feature_eprescriptions_enabled')->value('value'));
    }

    public function test_feature_middleware_blocks_access_when_disabled()
    {
        // Disable the feature
        Setting::where('key', 'feature_eprescriptions_enabled')->update(['value' => 'false']);
        Cache::forget('feature_eprescriptions_enabled');

        $user = User::factory()->create();
        $role = \App\Models\Role::create([
            'name' => 'patient',
            'slug' => 'patient',
            'permissions' => [],
        ]);
        $user->role_id = $role->id;
        $user->save();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/patient/medical/prescriptions');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'The eprescriptions feature is currently disabled by the administrator.',
            ]);
    }

    public function test_feature_middleware_allows_access_when_enabled()
    {
        // Enable the feature
        Setting::where('key', 'feature_eprescriptions_enabled')->update(['value' => 'true']);
        Cache::forget('feature_eprescriptions_enabled');

        $user = User::factory()->create();
        $role = \App\Models\Role::firstOrCreate(
            ['slug' => 'patient'],
            ['name' => 'patient', 'permissions' => []]
        );
        $user->role_id = $role->id;
        $user->save();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/patient/medical/prescriptions');

        // Should be 200 or empty list, but definitely not 403
        $response->assertStatus(200);
    }
}
