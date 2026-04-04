<?php

namespace Tests\Feature\API\Chat;

use App\Models\Assessment;
use App\Models\User;
use App\Models\UserAssessmentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatAssessmentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_conversation_with_assessments_and_analyze()
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

        $r1 = UserAssessmentResult::create([
            'user_id' => $user->id,
            'assessment_id' => $assessment->id,
            'answers' => ['a' => 1],
            'total_score' => 12,
            'interpretation' => 'ok',
            'completed_at' => now()->subDays(1),
        ]);

        $r2 = UserAssessmentResult::create([
            'user_id' => $user->id,
            'assessment_id' => $assessment->id,
            'answers' => ['a' => 2],
            'total_score' => 8,
            'interpretation' => 'ok',
            'completed_at' => now(),
        ]);

        // Bind a lightweight fake AI service to keep test deterministic
        $this->instance(\App\Services\AI\OpenAIService::class, new class
        {
            public function generateAssessmentInterpretation($name, $score, $answers)
            {
                return ['interpretation' => "Interp ({$score})", 'recommendations' => ["Rec {$score}"]];
            }
        });

        $createResp = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/chat/conversations', [
                'assessment_result_ids' => [$r1->id, $r2->id],
                'title' => 'Assessment chat',
            ]);

        $createResp->assertStatus(201);
        $convId = $createResp->json('data.conversation_id');
        $this->assertDatabaseHas('chat_conversations', ['id' => $convId]);
        $this->assertDatabaseHas('conversation_assessments', ['conversation_id' => $convId, 'assessment_result_id' => $r1->id]);

        $analyzeResp = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/chat/analyze-assessments', [
                'assessment_result_ids' => [$r1->id, $r2->id],
            ]);

        $analyzeResp->assertStatus(200);
        $analyzeResp->assertJsonStructure(['success', 'message', 'data' => ['analysis', 'insights', 'summaries']]);
    }
}
