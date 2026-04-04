<?php

namespace Tests\Feature\API;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Settings are seeded by migration, but in tests using RefreshDatabase,
        // they might be wiped if the migration isn't part of the "fresh" state or if we need specific values.
        // RefreshDatabase rolls back transactions. The seed migration runs only on "migrate".
        // If RefreshDatabase is used, it usually runs migrations.
        // Let's explicitly ensure settings exist for the test.

        Setting::updateOrCreate(
            ['key' => 'feature_eprescriptions_enabled'],
            [
                'value' => 'true',
                'group' => 'features',
                'type' => 'boolean',
            ]
        );

        Cache::forget('app_config');
    }

    public function test_config_endpoint_returns_features()
    {
        $response = $this->getJson('/api/v1/config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'features' => [
                        'eprescriptions',
                    ],
                    'version',
                    'support_email',
                ],
                'message',
            ]);

        // Check specific value
        $this->assertTrue($response->json('data.features.eprescriptions'));
    }

    public function test_config_reflects_feature_changes()
    {
        Setting::where('key', 'feature_eprescriptions_enabled')->update(['value' => 'false']);
        Cache::forget('app_config');

        $response = $this->getJson('/api/v1/config');

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.features.eprescriptions'));
    }
}
