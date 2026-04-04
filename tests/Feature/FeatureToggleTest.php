<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeatureToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    /** @test */
    public function feature_middleware_blocks_access_when_disabled()
    {
        // Middleware usage: 'feature:test_flag' -> looks for 'feature_test_flag_enabled'
        \Illuminate\Support\Facades\Route::middleware(['api', 'auth:sanctum', 'feature:test_flag'])
            ->get('/api/test/feature-protected', function () {
                return response()->json(['message' => 'Access Granted']);
            });

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // 1. Test Disabled
        Setting::updateOrCreate(
            ['key' => 'feature_test_flag_enabled'],
            ['value' => false, 'type' => 'boolean', 'group' => 'features']
        );

        $response = $this->getJson('/api/test/feature-protected');
        $response->assertStatus(403);

        // 2. Test Enabled
        Setting::updateOrCreate(
            ['key' => 'feature_test_flag_enabled'],
            ['value' => true, 'type' => 'boolean', 'group' => 'features']
        );

        // Clear cache as middleware uses cache
        \Illuminate\Support\Facades\Cache::forget('feature_test_flag_enabled');

        $response = $this->getJson('/api/test/feature-protected');
        $response->assertStatus(200)
            ->assertJson(['message' => 'Access Granted']);
    }
}
