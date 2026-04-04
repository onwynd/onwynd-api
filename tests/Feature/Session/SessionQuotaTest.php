<?php

namespace Tests\Feature\Session;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Therapist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_book_more_sessions_than_plan_allows()
    {
        config(['queue.default' => 'sync']);
        config(['mail.default' => 'array']);
        $patient = User::factory()->create([
            'first_name' => 'Pat',
            'last_name' => 'Tient',
            'email' => 'patient@example.com',
            'password' => 'password',
        ]);

        $therapistUser = User::factory()->create([
            'first_name' => 'Thera',
            'last_name' => 'Pist',
            'email' => 'therapist@example.com',
            'password' => 'password',
            'uuid' => (string) Str::uuid(),
        ]);

        $therapist = Therapist::create([
            'user_id' => $therapistUser->id,
            'status' => 'active',
            'specializations' => ['general'],
            'experience_years' => 3,
            'hourly_rate' => 15000,
            'bio' => 'Test',
            'license_number' => 'TEST-123',
            'license_state' => 'NG',
            'license_expiry' => now()->addYear()->toDateString(),
            'qualifications' => [['degree' => 'BSc', 'year' => 2020]],
            'languages' => ['en'],
            'currency' => 'NGN',
            'is_verified' => true,
            'verified_at' => now(),
            'is_accepting_clients' => true,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Basic',
            'slug' => 'basic',
            'description' => 'Basic plan',
            'price' => 10000,
            'currency' => 'NGN',
            'billing_interval' => 'monthly',
            'features' => [],
            'max_sessions' => 1,
            'trial_days' => 0,
            'is_active' => true,
        ]);

        $sub = Subscription::create([
            'user_id' => $patient->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
            'auto_renew' => true,
        ]);

        Sanctum::actingAs($patient);

        $first = $this->postJson('/api/v1/sessions/book', [
            'therapist_uuid' => $therapistUser->uuid,
            'scheduled_at' => now()->addDays(1)->toISOString(),
            'session_type' => 'video',
            'duration_minutes' => 60,
        ]);
        $first->dump();
        $first->assertStatus(201);
        $this->assertDatabaseCount('therapy_sessions', 1);

        $second = $this->postJson('/api/v1/sessions/book', [
            'therapist_uuid' => $therapistUser->uuid,
            'scheduled_at' => now()->addDays(2)->toISOString(),
            'session_type' => 'video',
            'duration_minutes' => 60,
        ]);
        $second->dump();
        $second->assertStatus(400);
        $second->assertJsonFragment([
            'message' => 'Failed to book session: Session quota exceeded for current subscription period',
        ]);
    }
}
