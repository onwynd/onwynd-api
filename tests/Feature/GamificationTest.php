<?php

namespace Tests\Feature;

use App\Models\Gamification\Badge;
use App\Models\Gamification\Streak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles and permissions as they are needed for middleware/policies
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\BadgeSeeder::class);
    }

    /** @test */
    public function patient_can_access_gamification_profile()
    {
        $user = User::factory()->create();
        $user->assignRole('patient');

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/patient/gamification');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'badges',
                    'streak',
                    'challenges',
                    'next_badge',
                ],
                'message',
            ]);
    }

    /** @test */
    public function streak_is_created_for_new_user()
    {
        $user = User::factory()->create();
        $user->assignRole('patient');

        // Simulate activity via service directly or via an endpoint that triggers it
        // Since we don't have a direct endpoint that *only* updates streak, we can test the service or
        // check if the gamification endpoint initializes it (if it does).
        // Based on the code, updateStreak is called on activity.
        // Let's assume accessing the gamification profile might trigger a check or we manually trigger it.

        // For this test, let's manually invoke the service to verify logic
        $service = new \App\Services\GamificationService;
        $service->updateStreak($user);

        $this->assertDatabaseHas('streaks', [
            'user_id' => $user->id,
            'current_streak' => 1,
        ]);
    }

    /** @test */
    public function badges_are_returned_correctly()
    {
        $user = User::factory()->create();
        $user->assignRole('patient');

        $badge = Badge::first(); // From seeder
        $user->badges()->attach($badge->id, ['awarded_at' => now()]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/patient/gamification');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => $badge->name,
            ]);
    }
}
