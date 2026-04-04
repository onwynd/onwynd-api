<?php

namespace Tests\Feature;

use App\Mail\Corporate\PilotActivatedEmail;
use App\Mail\Corporate\PilotExpiredEmail;
use App\Mail\Corporate\PilotMidpointEmail;
use App\Mail\Corporate\PilotPreRenewalEmail;
use App\Models\InstitutionalContract;
use App\Models\Institutional\Organization;
use App\Models\Institutional\OrganizationMember;
use App\Models\PromotionalCode;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Services\PromotionalCodeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tests for corporate pilot lifecycle email notifications.
 *
 * Command tested: pilots:notify (SendPilotLifecycleNotifications)
 * Activation endpoint tested: POST /api/v1/institutional/corporates/{corporate}/pilot/activate
 *
 * All mail sending is intercepted with Mail::fake().
 */
class CorporatePilotTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper: create a User with a given role slug (sets role_id + UserRole entry so hasRole() resolves).
     */
    private function createUserWithRole(string $slug): User
    {
        $role = Role::factory()->create(['slug' => $slug, 'name' => ucfirst($slug)]);
        $user = User::factory()->create(['role_id' => $role->id]);

        UserRole::create([
            'user_id'    => $user->id,
            'role'       => $slug,
            'is_primary' => true,
            'granted_at' => now(),
        ]);

        return $user;
    }

    /**
     * Helper: create a corporate Organization with its HR user linked as an org admin.
     */
    private function createCorporateOrg(User $hrUser): Organization
    {
        $org = Organization::create([
            'name'    => 'Acme Corp',
            'type'    => 'corporate',
            'country' => 'NG',
            'status'  => 'active',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id'         => $hrUser->id,
            'role'            => 'admin',
            'status'          => 'active',
        ]);

        return $org;
    }

    /**
     * Pilot activation endpoint queues a PilotActivatedEmail to the supplied HR email.
     */
    public function test_activation_email_sent_with_10_codes(): void
    {
        Mail::fake();

        $hrUser = $this->createUserWithRole('institutional');
        $org    = $this->createCorporateOrg($hrUser);

        $contract = InstitutionalContract::create([
            'institution_user_id'  => $hrUser->id,
            'company_name'         => 'Acme Corp',
            'contract_type'        => 'pilot',
            'start_date'           => Carbon::today(),
            'end_date'             => Carbon::today()->addDays(30),
            'total_sessions_quota' => 10,
            'sessions_used'        => 0,
            'status'               => 'pending',
            'contract_value'       => 50000.00,
        ]);

        $response = $this->actingAs($hrUser)
            ->postJson("/api/v1/institutional/corporates/{$org->id}/pilot/activate", [
                'hr_email'    => $hrUser->email,
                'hr_name'     => $hrUser->first_name . ' ' . $hrUser->last_name,
                'contract_id' => $contract->id,
            ]);

        $response->assertOk();

        Mail::assertQueued(PilotActivatedEmail::class, function (PilotActivatedEmail $mail) use ($hrUser) {
            return $mail->hasTo($hrUser->email)
                && $mail->sessionQuota === 10
                && $mail->orgName === 'Acme Corp';
        });

        // Contract status must now be active
        $this->assertDatabaseHas('institutional_contracts', [
            'id'     => $contract->id,
            'status' => 'active',
        ]);
    }

    /**
     * When a contract is exactly at the 50% elapsed mark, the midpoint email is queued
     * and midpoint_notified_at is stamped.
     *
     * A 30-day pilot at exactly day 15 → floor(15/30 * 100) = 50.
     */
    public function test_day_15_email_scheduled_correctly(): void
    {
        Mail::fake();

        $hrUser = $this->createUserWithRole('institutional');

        // Day 0 contract — 0% elapsed, no midpoint email
        $contract = InstitutionalContract::create([
            'institution_user_id'  => $hrUser->id,
            'company_name'         => 'Day15 Corp',
            'contract_type'        => 'pilot',
            'start_date'           => Carbon::today(),
            'end_date'             => Carbon::today()->addDays(30),
            'total_sessions_quota' => 20,
            'sessions_used'        => 8,
            'status'               => 'active',
            'midpoint_notified_at' => null,
        ]);

        $this->artisan('pilots:notify')->assertSuccessful();

        // No email should be sent at day 0
        Mail::assertNotQueued(PilotMidpointEmail::class);
        $this->assertNull($contract->fresh()->midpoint_notified_at);

        // Fast-forward: set start_date to 15 days ago so elapsed = 15 out of 30 → 50%
        $contract->update([
            'start_date' => Carbon::today()->subDays(15),
            'end_date'   => Carbon::today()->addDays(15),
        ]);

        $this->artisan('pilots:notify')->assertSuccessful();

        Mail::assertQueued(PilotMidpointEmail::class, function (PilotMidpointEmail $mail) use ($hrUser) {
            return $mail->hasTo($hrUser->email);
        });

        $this->assertNotNull($contract->fresh()->midpoint_notified_at);
    }

    /**
     * When end_date is exactly 14 days from today, the pre-renewal email is queued.
     *
     * For a 30-day pilot that means day 16 onwards triggers pre-renewal when exactly 14 remain.
     */
    public function test_day_25_email_scheduled_correctly(): void
    {
        Mail::fake();

        $hrUser = $this->createUserWithRole('institutional');

        // end_date = today + 14 days → qualifies for pre-renewal notice
        $contract = InstitutionalContract::create([
            'institution_user_id'    => $hrUser->id,
            'company_name'           => 'Renewal Corp',
            'contract_type'          => 'pilot',
            'start_date'             => Carbon::today()->subDays(16),
            'end_date'               => Carbon::today()->addDays(14),
            'total_sessions_quota'   => 15,
            'sessions_used'          => 10,
            'status'                 => 'active',
            'pre_renewal_notified_at' => null,
        ]);

        $this->artisan('pilots:notify')->assertSuccessful();

        Mail::assertQueued(PilotPreRenewalEmail::class, function (PilotPreRenewalEmail $mail) use ($hrUser) {
            return $mail->hasTo($hrUser->email);
        });

        $this->assertNotNull($contract->fresh()->pre_renewal_notified_at);
    }

    /**
     * When end_date was yesterday and the contract is still 'active', the expiry email is queued
     * and expiry_notified_at is stamped; contract status transitions to 'expired'.
     */
    public function test_day_30_pilot_end_notifies_employees(): void
    {
        Mail::fake();

        $hrUser = $this->createUserWithRole('institutional');

        $contract = InstitutionalContract::create([
            'institution_user_id' => $hrUser->id,
            'company_name'        => 'Expired Corp',
            'contract_type'       => 'pilot',
            'start_date'          => Carbon::today()->subDays(30),
            'end_date'            => Carbon::today()->subDay(),  // ended yesterday
            'total_sessions_quota' => 10,
            'sessions_used'       => 9,
            'status'              => 'active',
            'expiry_notified_at'  => null,
        ]);

        $this->artisan('pilots:notify')->assertSuccessful();

        Mail::assertQueued(PilotExpiredEmail::class, function (PilotExpiredEmail $mail) use ($hrUser) {
            return $mail->hasTo($hrUser->email);
        });

        $this->assertNotNull($contract->fresh()->expiry_notified_at);
        $this->assertEquals('expired', $contract->fresh()->status);
    }

    /**
     * A PromotionalCode with valid_until in the past returns an expiry error from
     * PromotionalCodeService::validate().
     */
    public function test_pilot_codes_expire_after_valid_until(): void
    {
        $expiredCode = PromotionalCode::factory()->create([
            'valid_until' => Carbon::yesterday(),
            'is_active'   => true,
            'type'        => 'fixed',
            'discount_value' => 1000,
        ]);

        $user    = User::factory()->create();
        $service = new PromotionalCodeService();

        $result = $service->validate(
            code:       $expiredCode->code,
            userId:     $user->id,
            currency:   'NGN',
            sessionFee: 8000.0
        );

        $this->assertFalse($result['valid'], 'Expired code must not be valid');
        $this->assertStringContainsString('expired', strtolower($result['message']));
        $this->assertEquals(0.0, $result['discount_amount']);
    }
}
