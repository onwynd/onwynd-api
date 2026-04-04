<?php

namespace Tests\Feature\Assessment;

use App\Models\Assessment;
use App\Models\User;
use App\Models\UserAssessmentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_results_returns_limited_list()
    {
        $user = User::factory()->create();
        $assessment = Assessment::create([
            'title' => 'Test Assessment',
            'slug' => 'test-assessment',
            'description' => 'Created by test',
            'type' => 'general',
            'is_active' => true,
            'total_questions' => 1,
            'scoring_method' => json_encode(['method' => 'sum']),
            'interpretation_guide' => json_encode([]),
        ]);

        // create 6 results
        for ($i = 0; $i < 6; $i++) {
            UserAssessmentResult::create([
                'user_id' => $user->id,
                'assessment_id' => $assessment->id,
                'answers' => ['q' => 1],
                'total_score' => 10 + $i,
                'interpretation' => 'ok',
                'completed_at' => now()->subDays(6 - $i),
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/assessments/results/recent?limit=3');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertEquals(15, $data[0]['total_score']);
    }

    public function test_delete_result_enforces_ownership()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $assessment = Assessment::create([
            'title' => 'Ownership Test',
            'slug' => 'ownership-test',
            'description' => 'Created by test',
            'type' => 'general',
            'is_active' => true,
            'total_questions' => 1,
            'scoring_method' => json_encode(['method' => 'sum']),
            'interpretation_guide' => json_encode([]),
        ]);

        $result = UserAssessmentResult::create([
            'user_id' => $userA->id,
            'assessment_id' => $assessment->id,
            'answers' => ['q' => 1],
            'total_score' => 42,
            'interpretation' => 'ok',
            'completed_at' => now(),
        ]);

        $this->actingAs($userB, 'sanctum')
            ->deleteJson("/api/v1/assessments/results/{$result->id}")
            ->assertStatus(403);

        $this->actingAs($userA, 'sanctum')
            ->deleteJson("/api/v1/assessments/results/{$result->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('user_assessment_results', ['id' => $result->id]);
    }
}
