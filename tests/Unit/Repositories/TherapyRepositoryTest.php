<?php

namespace Tests\Unit\Repositories;

use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use App\Repositories\Eloquent\TherapyEloquentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TherapyRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TherapyEloquentRepository(new TherapySession);
    }

    public function test_it_can_find_upcoming_sessions()
    {
        $user = User::factory()->create();
        $therapist = User::factory()->create();

        TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $user->id,
            'therapist_id' => $therapist->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'session_type' => 'video',
            'duration_minutes' => 60,
            'session_rate' => 100,
            'payment_status' => 'paid',
        ]);

        $results = $this->repository->findUpcomingSessions($user->id);

        $this->assertCount(1, $results);
    }

    public function test_it_can_get_session_history()
    {
        $user = User::factory()->create();
        $therapist = User::factory()->create();

        TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $user->id,
            'therapist_id' => $therapist->id,
            'scheduled_at' => now()->subDay(),
            'status' => 'completed',
            'session_type' => 'video',
            'duration_minutes' => 60,
            'session_rate' => 100,
            'payment_status' => 'paid',
        ]);

        $results = $this->repository->getSessionHistory($user->id);

        $this->assertCount(1, $results);
    }

    public function test_it_can_get_therapist_sessions()
    {
        $therapist = User::factory()->create();
        $patient = User::factory()->create();

        TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'therapist_id' => $therapist->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'session_type' => 'video',
            'duration_minutes' => 60,
            'session_rate' => 100,
            'payment_status' => 'paid',
        ]);

        $results = $this->repository->getTherapistSessions($therapist->id);

        $this->assertCount(1, $results);
    }

    public function test_it_can_get_patient_sessions()
    {
        $patient = User::factory()->create();
        $therapist = User::factory()->create();

        TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'therapist_id' => $therapist->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'session_type' => 'video',
            'duration_minutes' => 60,
            'session_rate' => 100,
            'payment_status' => 'paid',
        ]);

        $results = $this->repository->getPatientSessions($patient->id);

        $this->assertCount(1, $results);
    }

    public function test_it_can_get_therapist_stats()
    {
        $therapist = User::factory()->create();
        $patient = User::factory()->create();

        // Upcoming
        TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'therapist_id' => $therapist->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'session_type' => 'video',
            'duration_minutes' => 60,
            'session_rate' => 100,
            'payment_status' => 'paid',
        ]);

        // Today (Upcoming if later today, but here we just check count)
        // If we want this to be upcoming, it must be > now().
        // If we want it to be just "today", it matches whereDate(today).
        TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'therapist_id' => $therapist->id,
            'scheduled_at' => now(), // This might be slightly in past by the time query runs
            'status' => 'scheduled',
            'session_type' => 'video',
            'duration_minutes' => 60,
            'session_rate' => 100,
            'payment_status' => 'paid',
        ]);

        $stats = $this->repository->getTherapistStats($therapist->id);

        $this->assertEquals(1, $stats['upcoming_sessions_count']);
        $this->assertEquals(1, $stats['today_sessions']->count());
        $this->assertEquals(1, $stats['total_patients']);
    }

    public function test_it_has_relationship()
    {
        $therapist = User::factory()->create();
        $patient = User::factory()->create();

        TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'therapist_id' => $therapist->id,
            'scheduled_at' => now(),
            'status' => 'completed',
            'session_type' => 'video',
            'duration_minutes' => 60,
            'session_rate' => 100,
            'payment_status' => 'paid',
        ]);

        $this->assertTrue($this->repository->hasRelationship($therapist->id, $patient->id));
        $this->assertFalse($this->repository->hasRelationship($therapist->id, 9999));
    }

    public function test_it_can_get_shared_sessions()
    {
        $therapist = User::factory()->create();
        $patient = User::factory()->create();

        TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'therapist_id' => $therapist->id,
            'scheduled_at' => now(),
            'status' => 'completed',
            'session_type' => 'video',
            'duration_minutes' => 60,
            'session_rate' => 100,
            'payment_status' => 'paid',
        ]);

        $results = $this->repository->getSharedSessions($therapist->id, $patient->id);
        $this->assertCount(1, $results);
    }

    public function test_it_can_get_session_history_with_limit()
    {
        $user = User::factory()->create();
        $therapist = User::factory()->create();

        // Create 2 sessions
        TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $user->id,
            'therapist_id' => $therapist->id,
            'scheduled_at' => now()->subDays(2),
            'status' => 'completed',
            'session_type' => 'video',
            'duration_minutes' => 60,
            'session_rate' => 100,
            'payment_status' => 'paid',
        ]);
        TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $user->id,
            'therapist_id' => $therapist->id,
            'scheduled_at' => now()->subDays(1),
            'status' => 'completed',
            'session_type' => 'video',
            'duration_minutes' => 60,
            'session_rate' => 100,
            'payment_status' => 'paid',
        ]);

        $results = $this->repository->getSessionHistory($user->id, 1);

        $this->assertCount(1, $results);
    }

    public function test_it_can_get_patient_stats()
    {
        $patient = User::factory()->create();
        $therapist = User::factory()->create();

        TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'therapist_id' => $therapist->id,
            'scheduled_at' => now()->subDay(),
            'status' => 'completed',
            'session_type' => 'video',
            'duration_minutes' => 60,
            'session_rate' => 100,
            'payment_status' => 'paid',
        ]);

        $stats = $this->repository->getPatientStats($patient->id);
        $this->assertEquals(1, $stats['completed_sessions']);
    }

    public function test_it_can_update_session_note()
    {
        $patient = User::factory()->create();
        $therapist = User::factory()->create();

        $session = TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'therapist_id' => $therapist->id,
            'scheduled_at' => now(),
            'status' => 'completed',
            'session_type' => 'video',
            'duration_minutes' => 60,
            'session_rate' => 100,
            'payment_status' => 'paid',
        ]);

        $note = $this->repository->updateSessionNote($session->id, [
            'therapist_id' => $therapist->id,
            'session_summary' => 'Summary',
            'observations' => 'Observations',
        ]);

        $this->assertNotNull($note);
        $this->assertEquals('Summary', $note->session_summary);
    }

    public function test_it_can_get_therapist_patients()
    {
        $therapistUser = User::factory()->create();

        $patient1 = User::factory()->create(['first_name' => 'Patient', 'last_name' => 'One']);
        $patient2 = User::factory()->create(['first_name' => 'Patient', 'last_name' => 'Two']);

        // Session for Patient 1
        TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $patient1->id,
            'therapist_id' => $therapistUser->id,
            'scheduled_at' => now()->subDay(),
            'status' => 'completed',
            'session_type' => 'video',
            'duration_minutes' => 60,
            'session_rate' => 100,
            'payment_status' => 'paid',
            'ended_at' => now()->subDay()->addHour(),
        ]);

        // Session for Patient 2
        TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $patient2->id,
            'therapist_id' => $therapistUser->id,
            'scheduled_at' => now()->subDay(),
            'status' => 'completed',
            'session_type' => 'video',
            'duration_minutes' => 60,
            'session_rate' => 100,
            'payment_status' => 'paid',
            'ended_at' => now()->subDay()->addHour(),
        ]);

        $results = $this->repository->getTherapistPatients($therapistUser->id);

        $this->assertCount(2, $results);
    }

    public function test_it_can_get_therapist_earnings()
    {
        $therapistUser = User::factory()->create();
        $patient = User::factory()->create();

        TherapySession::create([
            'uuid' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'therapist_id' => $therapistUser->id,
            'scheduled_at' => now(), // This month
            'ended_at' => now(),
            'status' => 'completed',
            'session_type' => 'video',
            'duration_minutes' => 60,
            'session_rate' => 150.00,
            'payment_status' => 'paid',
        ]);

        $earnings = $this->repository->getTherapistEarnings($therapistUser->id);

        $this->assertEquals(150.00, $earnings['earnings_this_month']);
        $this->assertEquals(1, $earnings['breakdown']['total_sessions_month']);
    }

    public function test_it_can_get_available_therapists()
    {
        // Create a therapist
        $therapist = Therapist::factory()->create([
            'status' => 'active',
            'specializations' => ['CBT'],
            'verified_at' => now(),
        ]);

        $filters = ['specialization' => 'CBT'];
        $results = $this->repository->getAvailableTherapists($filters);

        $this->assertCount(1, $results);
        $this->assertEquals($therapist->id, $results[0]['id']);
    }

    public function test_it_can_find_therapist()
    {
        $therapist = Therapist::factory()->create();

        $found = $this->repository->findTherapist($therapist->id);

        $this->assertNotNull($found);
        $this->assertEquals($therapist->id, $found->id);
    }
}
