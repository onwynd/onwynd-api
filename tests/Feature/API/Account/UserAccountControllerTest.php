<?php

namespace Tests\Feature\API\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'password' => Hash::make('SecurePassword123!'),
        ]);
    }

    /**
     * Test getting user profile
     */
    public function test_get_profile()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/account/profile');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'full_name',
                'email',
                'phone',
                'role',
            ],
        ]);
    }

    /**
     * Test updating user profile
     */
    public function test_update_profile()
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/account/profile', [
                'full_name' => 'Jane Doe Updated',
                'phone' => '+234801234567',
                'bio' => 'Updated bio',
                'gender' => 'female',
                'city' => 'Lagos',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'full_name' => 'Jane Doe Updated',
        ]);
    }

    /**
     * Test changing password
     */
    public function test_change_password()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/account/change-password', [
                'current_password' => 'SecurePassword123!',
                'new_password' => 'NewSecurePassword456!',
                'new_password_confirmation' => 'NewSecurePassword456!',
            ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('NewSecurePassword456!', User::find($this->user->id)->password));
    }

    /**
     * Test wrong current password
     */
    public function test_wrong_current_password()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/account/change-password', [
                'current_password' => 'WrongPassword123!',
                'new_password' => 'NewSecurePassword456!',
                'new_password_confirmation' => 'NewSecurePassword456!',
            ]);

        $response->assertStatus(401);
    }

    /**
     * Test weak password
     */
    public function test_weak_password()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/account/change-password', [
                'current_password' => 'SecurePassword123!',
                'new_password' => 'weak',
                'new_password_confirmation' => 'weak',
            ]);

        $response->assertStatus(400);
    }

    /**
     * Test updating email
     */
    public function test_update_email()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/account/change-email', [
                'new_email' => 'newemail@example.com',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => 'newemail@example.com',
        ]);
    }

    /**
     * Test email already in use
     */
    public function test_email_already_in_use()
    {
        $otherUser = User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/account/change-email', [
                'new_email' => 'existing@example.com',
            ]);

        $response->assertStatus(400);
    }

    /**
     * Test enabling two-factor authentication
     */
    public function test_enable_two_factor()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/account/two-factor/enable', [
                'password' => 'SecurePassword123!',
            ]);

        $response->assertStatus(200);
        $this->assertTrue(User::find($this->user->id)->two_factor_enabled);
    }

    /**
     * Test wrong password for 2FA
     */
    public function test_wrong_password_for2_fa()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/account/two-factor/enable', [
                'password' => 'WrongPassword123!',
            ]);

        $response->assertStatus(401);
    }

    /**
     * Test disabling two-factor authentication
     */
    public function test_disable_two_factor()
    {
        $this->user->update(['two_factor_enabled' => true]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/account/two-factor/disable', [
                'password' => 'SecurePassword123!',
            ]);

        $response->assertStatus(200);
        $this->assertFalse(User::find($this->user->id)->two_factor_enabled);
    }

    /**
     * Test getting notification settings
     */
    public function test_get_notification_settings()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/account/notification-settings');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user_id',
                'settings',
            ],
        ]);
    }

    /**
     * Test updating notification settings
     */
    public function test_update_notification_settings()
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/account/notification-settings', [
                'email_notifications' => true,
                'sms_notifications' => false,
                'session_reminders' => true,
                'promotional_emails' => false,
            ]);

        $response->assertStatus(200);
    }

    /**
     * Test getting account history
     */
    public function test_get_account_history()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/account/history');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'history',
                'pagination',
            ],
        ]);
    }

    /**
     * Test requesting account deletion
     */
    public function test_request_account_deletion()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/account/delete', [
                'password' => 'SecurePassword123!',
            ]);

        $response->assertStatus(200);
        $this->assertTrue(User::find($this->user->id)->marked_for_deletion);
    }

    /**
     * Test wrong password for deletion
     */
    public function test_wrong_password_for_deletion()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/account/delete', [
                'password' => 'WrongPassword123!',
            ]);

        $response->assertStatus(401);
    }

    /**
     * Test cancelling account deletion
     */
    public function test_cancel_account_deletion()
    {
        $this->user->update(['marked_for_deletion' => true]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/account/cancel-deletion');

        $response->assertStatus(200);
        $this->assertFalse(User::find($this->user->id)->marked_for_deletion);
    }

    /**
     * Test no pending deletion request
     */
    public function test_no_pending_deletion_request()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/account/cancel-deletion');

        $response->assertStatus(400);
    }

    /**
     * Test unauthenticated access
     */
    public function test_unauthenticated_access()
    {
        $response = $this->getJson('/api/v1/account/profile');

        $response->assertStatus(401);
    }

    /**
     * Test invalid email format
     */
    public function test_invalid_email_format()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/account/change-email', [
                'new_email' => 'invalid-email',
            ]);

        $response->assertStatus(400);
    }

    /**
     * Test password mismatch
     */
    public function test_password_mismatch()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/account/change-password', [
                'current_password' => 'SecurePassword123!',
                'new_password' => 'NewSecurePassword456!',
                'new_password_confirmation' => 'DifferentPassword789!',
            ]);

        $response->assertStatus(400);
    }

    /**
     * Test updating profile with avatar
     */
    public function test_update_profile_with_avatar()
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/account/profile', [
                'full_name' => 'Updated Name',
                'avatar' => 'avatar.jpg',
            ]);

        // Note: In actual test with file upload, use Storage::fake() and UploadedFile
        // This is simplified for demonstration
        $response->assertStatus(200);
    }

    /**
     * Test getting therapist-specific profile data
     */
    public function test_get_therapist_profile_data()
    {
        $therapistUser = User::factory()->create(['role' => 'therapist']);

        $response = $this->actingAs($therapistUser)
            ->getJson('/api/v1/account/profile');

        $response->assertStatus(200);
        $response->assertJsonPath('data.role', 'therapist');
    }
}
