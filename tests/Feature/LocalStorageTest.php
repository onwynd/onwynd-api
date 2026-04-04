<?php

namespace Tests\Feature;

use App\Models\Role; // Correct import
use App\Models\TherapistProfile;
use App\Models\Therapy\VideoSession;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Therapy\VideoRecordingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed basic roles
        Role::create(['name' => 'Therapist', 'slug' => 'therapist', 'permissions' => []]);
        Role::create(['name' => 'Patient', 'slug' => 'patient', 'permissions' => []]);
        Role::create(['name' => 'Admin', 'slug' => 'admin', 'permissions' => []]);
        Role::create(['name' => 'Clinical Advisor', 'slug' => 'clinical_advisor', 'permissions' => []]);

        // Mock feature flag to be enabled
        Config::set('features.secure_documents_enabled', true);
    }

    public function test_therapist_certificate_upload_uses_correct_folder_structure()
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        $user->assignRole('therapist');

        // Ensure profile exists
        $profile = TherapistProfile::create([
            'user_id' => $user->id,
            'is_verified' => false,
            'license_number' => 'LIC123',
            'experience_years' => 5,
            'license_state' => 'NY', // Add state
            'license_expiry' => now()->addYear(), // Add expiry
            'specializations' => ['Anxiety', 'Depression'],
            'qualifications' => ['PhD'],
            'languages' => ['English'],
            'hourly_rate' => 100.00,
            'bio' => 'Experienced therapist',
        ]);

        $file = UploadedFile::fake()->create('certificate.pdf', 100);

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/v1/therapist/profile', [
                '_method' => 'PUT',
                'certificate' => $file,
            ]);

        $response->assertStatus(200);

        // Controller uses $therapist->user->name (John Doe -> John_Doe) and profile created_at
        $folderName = 'John_Doe_'.$profile->created_at->format('Y-m-d');

        // Find the file in the expected directory
        $files = Storage::disk('local')->allFiles("documents/therapists/{$folderName}");

        $this->assertNotEmpty($files, "Certificate not found in expected folder: documents/therapists/{$folderName}");
    }

    public function test_video_recording_service_uses_correct_folder_structure()
    {
        Storage::fake('local');

        $therapySession = TherapySession::factory()->create();
        $videoSession = VideoSession::create([
            'therapy_session_id' => $therapySession->id,
            'host_id' => $therapySession->therapist_id,
            'participant_id' => $therapySession->patient_id,
            'status' => 'scheduled',
        ]);

        $service = new VideoRecordingService;
        $file = UploadedFile::fake()->create('recording.webm', 1000);

        $recording = $service->storeRecording($videoSession, $file);

        $expectedPathPart = "documents/sessions/{$therapySession->id}/recordings";
        $this->assertStringContainsString($expectedPathPart, $recording->storage_path);

        Storage::disk('local')->assertExists($recording->storage_path);
    }

    public function test_secure_document_upload_with_session_id_uses_correct_folder()
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->assignRole('patient'); // Assign patient role

        $therapySession = TherapySession::factory()->create();

        $file = UploadedFile::fake()->create('notes.pdf', 100);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/patient/documents', [
                'file' => $file,
                'title' => 'Session Notes',
                'therapy_session_id' => $therapySession->id,
            ]);

        $response->assertStatus(201);

        $expectedPathPart = "documents/sessions/{$therapySession->id}";
        $document = $response->json('data');

        $this->assertStringContainsString($expectedPathPart, $document['file_path']);
        Storage::disk('local')->assertExists($document['file_path']);
    }

    public function test_admin_can_view_therapist_document()
    {
        Storage::fake('local');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $therapist = User::factory()->create();
        $therapist->assignRole('therapist');

        // Create a dummy file
        $path = 'documents/therapists/test_user/cert.pdf';
        Storage::disk('local')->put($path, 'content');

        TherapistProfile::create([
            'user_id' => $therapist->id,
            'certificate_url' => $path,
            'is_verified' => false,
            'license_number' => 'LIC123',
            'experience_years' => 5,
            'license_state' => 'NY',
            'license_expiry' => now()->addYear(),
            'specializations' => ['Anxiety', 'Depression'],
            'qualifications' => ['PhD'],
            'languages' => ['English'],
            'hourly_rate' => 100.00,
            'bio' => 'Experienced therapist',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/admin/therapists/{$therapist->id}/documents/certificate");

        $response->assertStatus(200);
        $response->assertHeader('content-type');
    }

    public function test_clinical_advisor_can_view_therapist_document()
    {
        Storage::fake('local');

        $advisor = User::factory()->create();
        $advisor->assignRole('clinical_advisor');

        $therapist = User::factory()->create();
        $therapist->assignRole('therapist');

        // Create a dummy file
        $path = 'documents/therapists/test_user/cert.pdf';
        Storage::disk('local')->put($path, 'content');

        TherapistProfile::create([
            'user_id' => $therapist->id,
            'certificate_url' => $path,
            'is_verified' => false,
            'license_number' => 'LIC123',
            'experience_years' => 5,
            'license_state' => 'NY',
            'license_expiry' => now()->addYear(),
            'specializations' => ['Anxiety', 'Depression'],
            'qualifications' => ['PhD'],
            'languages' => ['English'],
            'hourly_rate' => 100.00,
            'bio' => 'Experienced therapist',
        ]);

        $response = $this->actingAs($advisor, 'sanctum')
            ->get("/api/v1/admin/therapists/{$therapist->id}/documents/certificate");

        $response->assertStatus(200);
    }
}
