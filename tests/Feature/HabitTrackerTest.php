<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\BadgeAwarded;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum; // Add this import
use Tests\TestCase;

class HabitTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected $patient;

    protected $rolePatient;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['name' => 'patient', 'slug' => 'patient']);
        $this->patient = User::factory()->create(['role_id' => $role->id]);
    }

    public function test_user_can_create_habit()
    {
        Sanctum::actingAs($this->patient, ['*']);

        $response = $this->postJson('/api/v1/patient/habits', [
            'name' => 'Drink Water',
            'frequency' => 'daily',
            'start_date' => now()->toDateString(),
            'target_count' => 8,
        ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Drink Water');

        $this->assertDatabaseHas('habits', [
            'user_id' => $this->patient->id,
            'name' => 'Drink Water',
        ]);
    }

    public function test_streak_increases_on_consecutive_logs()
    {
        Sanctum::actingAs($this->patient, ['*']);

        $habit = Habit::factory()->create([
            'user_id' => $this->patient->id,
            'streak' => 0,
            'longest_streak' => 0,
        ]);

        // Day 1
        $date1 = Carbon::now()->subDays(1)->toDateString();
        $this->postJson("/api/v1/patient/habits/{$habit->id}/log", [
            'date' => $date1,
            'status' => 'completed',
            'count' => 1,
        ])->assertStatus(200);

        $this->assertEquals(1, $habit->fresh()->streak);

        // Day 2 (Today)
        $date2 = Carbon::now()->toDateString();
        $this->postJson("/api/v1/patient/habits/{$habit->id}/log", [
            'date' => $date2,
            'status' => 'completed',
            'count' => 1,
        ])->assertStatus(200);

        $this->assertEquals(2, $habit->fresh()->streak);
    }

    public function test_streak_resets_on_missed_day()
    {
        Sanctum::actingAs($this->patient, ['*']);

        $habit = Habit::factory()->create([
            'user_id' => $this->patient->id,
            'streak' => 5,
            'longest_streak' => 5,
        ]);

        // Create a log for 2 days ago
        HabitLog::create([
            'habit_id' => $habit->id,
            'date' => Carbon::now()->subDays(2)->toDateString(),
            'status' => 'completed',
        ]);

        // Log for Today
        $this->postJson("/api/v1/patient/habits/{$habit->id}/log", [
            'date' => Carbon::now()->toDateString(),
            'status' => 'completed',
            'count' => 1,
        ])->assertStatus(200);

        // Streak should be 1 (because yesterday was missed)
        $this->assertEquals(1, $habit->fresh()->streak);
    }

    public function test_badge_awarded_notification()
    {
        Sanctum::actingAs($this->patient, ['*']);
        Notification::fake();

        $badge = Badge::create([
            'name' => 'First Step',
            'description' => 'Complete your first habit',
            'icon_url' => 'badge.png',
            'criteria_type' => 'count',
            'criteria_value' => 1,
        ]);

        $habit = Habit::factory()->create(['user_id' => $this->patient->id]);

        $this->postJson("/api/v1/patient/habits/{$habit->id}/log", [
            'date' => Carbon::now()->toDateString(),
            'status' => 'completed',
            'count' => 1,
        ])->assertStatus(200);

        Notification::assertSentTo(
            $this->patient,
            BadgeAwarded::class,
            function ($notification, $channels) use ($badge) {
                return $notification->toArray($this->patient)['badge_id'] === $badge->id;
            }
        );

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $this->patient->id,
            'badge_id' => $badge->id,
        ]);
    }
}
