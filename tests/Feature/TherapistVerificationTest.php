<?php

namespace Tests\Feature;

use App\Mail\TherapistDocumentRejection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TherapistVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_admin_can_reject_therapist_and_send_document_rejection_email()
    {
        Mail::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $therapist = User::factory()->create();
        $therapist->assignRole('therapist');

        // Create therapist profile
        $profileData = \Database\Factories\TherapistFactory::new()->make()->toArray();
        unset($profileData['user_id']); // Remove user_id to avoid conflicts

        $therapist->therapistProfile()->create(array_merge($profileData, [
            'is_verified' => false,
            'status' => 'pending',
        ]));

        Sanctum::actingAs($admin, ['*']);

        $reason = 'Uploaded ID is blurry.';

        $response = $this->postJson("/api/v1/admin/therapists/{$therapist->id}/reject", [
            'reason' => $reason,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Therapist rejected and notified via email.']);

        $this->assertDatabaseHas('therapist_profiles', [
            'user_id' => $therapist->id,
            'status' => 'rejected',
        ]);

        Mail::assertQueued(TherapistDocumentRejection::class, function ($mail) use ($therapist, $reason) {
            return $mail->hasTo($therapist->email) &&
                $mail->reason === $reason;
        });
    }

    public function test_admin_can_list_pending_verifications()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $therapist = User::factory()->create();
        $therapist->assignRole('therapist');

        $profileData = \Database\Factories\TherapistFactory::new()->make()->toArray();
        unset($profileData['user_id']);

        $therapist->therapistProfile()->create(array_merge($profileData, [
            'is_verified' => false,
            'status' => 'pending',
        ]));

        // Create an already verified therapist to ensure they don't appear
        $verifiedTherapist = User::factory()->create();
        $verifiedTherapist->assignRole('therapist');

        $verifiedProfileData = \Database\Factories\TherapistFactory::new()->make()->toArray();
        unset($verifiedProfileData['user_id']);

        $verifiedTherapist->therapistProfile()->create(array_merge($verifiedProfileData, [
            'is_verified' => true,
            'status' => 'approved',
        ]));

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/v1/admin/therapists/pending');

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $therapist->id])
            ->assertJsonMissing(['id' => $verifiedTherapist->id]);
    }
}
