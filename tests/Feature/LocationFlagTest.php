<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Therapist;
use App\Models\TherapistLocationMismatch;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\TherapistReverificationRequired;
use App\Services\Therapist\LocationVerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tests for the IP geolocation flagging system.
 *
 * LocationVerificationService::checkOnLogin() calls http://ip-api.com/json/{ip}
 * All external HTTP calls are mocked with Http::fake().
 *
 * Note: detectCountry() skips private/loopback IPs (FILTER_FLAG_NO_PRIV_RANGE |
 * FILTER_FLAG_NO_RES_RANGE). Tests must supply a routable public IP address in the
 * simulated request for the geo-lookup branch to execute.
 */
class LocationFlagTest extends TestCase
{
    use RefreshDatabase;

    protected User $therapistUser;
    protected Therapist $therapistProfile;

    /** A public IP address that passes PHP's private-range filter */
    private const PUBLIC_IP = '8.8.8.8';

    protected function setUp(): void
    {
        parent::setUp();

        $therapistRole = Role::factory()->create(['slug' => 'therapist', 'name' => 'Therapist']);

        $this->therapistUser = User::factory()->create(['role_id' => $therapistRole->id]);

        $this->therapistProfile = Therapist::factory()->create([
            'user_id'            => $this->therapistUser->id,
            'country_of_operation' => 'NG',
            'account_flagged'    => false,
            'status'             => 'approved',
        ]);
    }

    /**
     * A single mismatch (stored = NG, detected = US) should create a record but NOT flag the account.
     */
    public function test_single_mismatch_does_not_flag(): void
    {
        Http::fake([
            'http://ip-api.com/*' => Http::response([
                'status'      => 'success',
                'countryCode' => 'US',
            ], 200),
        ]);

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => self::PUBLIC_IP]);

        $service = new LocationVerificationService();
        $result  = $service->checkOnLogin($this->therapistProfile, $request);

        // Below threshold — should return null (no flag warning returned to caller)
        $this->assertNull($result, 'A single mismatch should not trigger a flag warning');

        // One mismatch record must exist
        $this->assertDatabaseHas('therapist_location_mismatches', [
            'therapist_id'     => $this->therapistProfile->id,
            'stored_country'   => 'NG',
            'detected_country' => 'US',
            'resolved'         => false,
        ]);

        // Account must NOT be flagged yet
        $this->therapistProfile->refresh();
        $this->assertFalse($this->therapistProfile->account_flagged);
    }

    /**
     * Three consecutive mismatches (stored = NG, detected = US) must flag the account
     * and set flag_reason = 'location_mismatch'.
     */
    public function test_three_consecutive_mismatches_flag_account(): void
    {
        Notification::fake();

        Http::fake([
            'http://ip-api.com/*' => Http::response([
                'status'      => 'success',
                'countryCode' => 'US',
            ], 200),
        ]);

        $service = new LocationVerificationService();

        // Call 1 & 2 — below threshold, should not flag
        for ($i = 0; $i < 2; $i++) {
            $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => self::PUBLIC_IP]);
            $service->checkOnLogin($this->therapistProfile->fresh(), $request);
        }

        $this->therapistProfile->refresh();
        $this->assertFalse($this->therapistProfile->account_flagged, 'Account must not be flagged after 2 mismatches');

        // Call 3 — must trigger the flag
        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => self::PUBLIC_IP]);
        $result  = $service->checkOnLogin($this->therapistProfile->fresh(), $request);

        $this->assertNotNull($result, 'Third mismatch must return a warning array');
        $this->assertTrue($result['flagged']);
        $this->assertNotEmpty($result['message']);

        $this->therapistProfile->refresh();
        $this->assertTrue($this->therapistProfile->account_flagged);
        $this->assertEquals('location_mismatch', $this->therapistProfile->flag_reason);
        $this->assertNotNull($this->therapistProfile->flagged_at);

        // Three mismatch records must exist
        $this->assertEquals(3, TherapistLocationMismatch::where('therapist_id', $this->therapistProfile->id)->count());
    }

    /**
     * Admin POST to resolve-location-flag with action='dismiss' should:
     * - Mark all unresolved mismatches as resolved=true
     * - Clear account_flagged on the therapist profile
     */
    public function test_admin_can_resolve_flag(): void
    {
        $adminRole = Role::factory()->create(['slug' => 'admin', 'name' => 'Admin']);
        $admin     = User::factory()->create(['role_id' => $adminRole->id]);

        // Give admin user the 'admin' role via UserRole table (hasRole checks both)
        UserRole::create([
            'user_id'    => $admin->id,
            'role'       => 'admin',
            'is_primary' => true,
            'granted_at' => now(),
        ]);

        // Mark the therapist user as having the 'therapist' role so resolveLocationFlag passes
        UserRole::create([
            'user_id'    => $this->therapistUser->id,
            'role'       => 'therapist',
            'is_primary' => true,
            'granted_at' => now(),
        ]);

        // Pre-seed flagged state and 3 unresolved mismatches
        $this->therapistProfile->update([
            'account_flagged' => true,
            'flag_reason'     => 'location_mismatch',
            'flagged_at'      => now(),
        ]);

        for ($i = 0; $i < 3; $i++) {
            TherapistLocationMismatch::create([
                'therapist_id'     => $this->therapistProfile->id,
                'stored_country'   => 'NG',
                'detected_country' => 'US',
                'ip_address'       => self::PUBLIC_IP,
                'detected_at'      => now()->subHours($i + 1),
                'resolved'         => false,
            ]);
        }

        // The route uses {therapist} which resolves to a User model
        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/therapists/{$this->therapistUser->id}/resolve-location-flag", [
                'action' => 'dismiss',
            ]);

        $response->assertOk();

        // All 3 mismatches must be resolved
        $unresolvedCount = TherapistLocationMismatch::where('therapist_id', $this->therapistProfile->id)
            ->where('resolved', false)
            ->count();
        $this->assertEquals(0, $unresolvedCount, 'All mismatch records must be marked resolved');

        $resolvedCount = TherapistLocationMismatch::where('therapist_id', $this->therapistProfile->id)
            ->where('resolved', true)
            ->count();
        $this->assertEquals(3, $resolvedCount);

        // Therapist must no longer be flagged
        $this->therapistProfile->refresh();
        $this->assertFalse($this->therapistProfile->account_flagged);
        $this->assertNull($this->therapistProfile->flag_reason);
    }

    /**
     * When the threshold is reached:
     * - Admin users are notified via the inline anonymous mail notification
     * - The therapist's own user receives a TherapistReverificationRequired notification
     */
    public function test_therapist_notified_on_flag(): void
    {
        Notification::fake();

        // Create an admin user so notifyAdmin() has someone to send to
        $adminRole = Role::factory()->create(['slug' => 'admin', 'name' => 'Admin']);
        $adminUser = User::factory()->create(['role_id' => $adminRole->id]);

        Http::fake([
            'http://ip-api.com/*' => Http::response([
                'status'      => 'success',
                'countryCode' => 'US',
            ], 200),
        ]);

        $service = new LocationVerificationService();

        // Seed 2 mismatches directly so the 3rd call pushes count to threshold
        for ($i = 0; $i < 2; $i++) {
            TherapistLocationMismatch::create([
                'therapist_id'     => $this->therapistProfile->id,
                'stored_country'   => 'NG',
                'detected_country' => 'US',
                'ip_address'       => self::PUBLIC_IP,
                'detected_at'      => now(),
                'resolved'         => false,
            ]);
        }

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => self::PUBLIC_IP]);
        $result  = $service->checkOnLogin($this->therapistProfile->fresh(), $request);

        $this->assertNotNull($result);
        $this->assertTrue($result['flagged']);

        // Admin notification must be dispatched via Notification::send()
        Notification::assertSentTo($adminUser, function ($notification) use ($adminUser) {
            return in_array('mail', $notification->via($adminUser));
        });

        // Therapist must also be notified directly via TherapistReverificationRequired
        Notification::assertSentTo($this->therapistUser, TherapistReverificationRequired::class);
    }
}
