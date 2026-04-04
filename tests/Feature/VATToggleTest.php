<?php

namespace Tests\Feature;

use App\Helpers\EmailAmountFormatter;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Services\PlatformSettingsService;
use App\Services\VATService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for VAT toggle and calculation.
 *
 * VATService reads from PlatformSettingsService which reads from the `settings` table
 * and caches results for 60 minutes. PlatformSettingsService::set() calls Cache::forget()
 * immediately after writing, so no manual cache clearing is needed between tests.
 *
 * Admin endpoint: POST /api/v1/admin/settings/vat/toggle  (requires role:admin)
 */
class VATToggleTest extends TestCase
{
    use RefreshDatabase;

    protected VATService $vatService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vatService = new VATService();
    }

    /**
     * When vat_enabled = false, VATService::calculate() must return vat_amount = 0
     * and vat_enabled = false.
     */
    public function test_vat_not_shown_when_disabled(): void
    {
        PlatformSettingsService::set('vat_enabled', 'false');

        $result = $this->vatService->calculate(10000);

        $this->assertFalse($result['vat_enabled']);
        $this->assertEquals(0.0, $result['vat_amount']);
        $this->assertEquals(10000.0, $result['total_with_vat'], 'Total must equal the original amount when VAT is off');
    }

    /**
     * When vat_enabled = true and vat_rate = 0.075, calculate(10000) must return
     * vat_amount = 750 and total_with_vat = 10750.
     */
    public function test_vat_calculated_correctly_when_enabled(): void
    {
        PlatformSettingsService::set('vat_enabled', 'true');
        PlatformSettingsService::set('vat_rate', '0.075');

        $result = $this->vatService->calculate(10000);

        $this->assertTrue($result['vat_enabled']);
        $this->assertEquals(0.075, $result['vat_rate']);
        $this->assertEquals(750.0, $result['vat_amount']);
        $this->assertEquals(10750.0, $result['total_with_vat']);
    }

    /**
     * EmailAmountFormatter::formatTotal respects the vat_enabled toggle.
     *
     * When enabled: vat_line must be a non-null formatted amount string.
     * When disabled: vat_line must be null.
     */
    public function test_vat_in_email_template_respects_toggle(): void
    {
        // VAT enabled
        PlatformSettingsService::set('vat_enabled', 'true');
        PlatformSettingsService::set('vat_rate', '0.075');
        PlatformSettingsService::set('vat_label', 'VAT (7.5%)');

        $enabledResult = EmailAmountFormatter::formatTotal(10000, 0, 0, 'NGN');

        $this->assertTrue($enabledResult['vat_enabled']);
        $this->assertNotNull($enabledResult['vat_line'], 'vat_line must be present when VAT is enabled');
        $this->assertStringContainsString('750', $enabledResult['vat_line'], 'vat_line must include the correct VAT amount');

        // VAT disabled
        PlatformSettingsService::set('vat_enabled', 'false');

        $disabledResult = EmailAmountFormatter::formatTotal(10000, 0, 0, 'NGN');

        $this->assertFalse($disabledResult['vat_enabled']);
        $this->assertNull($disabledResult['vat_line'], 'vat_line must be null when VAT is disabled');
    }

    /**
     * Admin can POST to the VAT toggle endpoint to flip the current vat_enabled value.
     * The response must reflect the toggled state.
     */
    public function test_admin_can_toggle_vat(): void
    {
        $adminRole = Role::factory()->create(['slug' => 'admin', 'name' => 'Admin']);
        $admin     = User::factory()->create(['role_id' => $adminRole->id]);

        UserRole::create([
            'user_id'    => $admin->id,
            'role'       => 'admin',
            'is_primary' => true,
            'granted_at' => now(),
        ]);

        // Start with VAT disabled
        PlatformSettingsService::set('vat_enabled', 'false');
        $before = $this->vatService->isEnabled();
        $this->assertFalse($before);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/settings/vat/toggle');

        $response->assertOk();

        $toggled = $response->json('data.vat_enabled');
        $this->assertTrue((bool) $toggled, 'After toggling from false the new value must be true');

        // Confirm the setting was actually persisted in the database
        $this->assertTrue($this->vatService->isEnabled(), 'VATService::isEnabled() must reflect the new value after toggle');

        // Toggle again — should go back to false
        $response2 = $this->actingAs($admin)
            ->postJson('/api/v1/admin/settings/vat/toggle');

        $response2->assertOk();
        $this->assertFalse((bool) $response2->json('data.vat_enabled'));
        $this->assertFalse($this->vatService->isEnabled());
    }
}
