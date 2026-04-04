<?php

namespace Tests\Feature\Patient;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure an authenticated patient can submit answers and we don't hit a 500
     * when the computed severity level doesn't match the database enum.
     */
    public function test_patient_submit_with_varied_severity_label()
    {
        // create patient role and user
        $role = Role::factory()->create(['slug' => 'patient', 'name' => 'Patient']);
        $user = User::factory()->create(['role_id' => $role->id]);

        // create an assessment with a recognizable title so mapping runs
        $assessment = Assessment::factory()->create(['title' => 'PHQ-9 Depression Scale']);

        // add a couple of questions (order_number important for scoring logic)
        $q1 = AssessmentQuestion::create([
            'assessment_id' => $assessment->id,
            'question_text' => 'Q1',
            'question_type' => 'scale',
            'order_number' => 1,
            'is_required' => true,
        ]);
        $q2 = AssessmentQuestion::create([
            'assessment_id' => $assessment->id,
            'question_text' => 'Q2',
            'question_type' => 'scale',
            'order_number' => 2,
            'is_required' => true,
        ]);

        // craft answers such that rawScore >= 15 but < 20 -> "Moderately Severe"
        // we just wind up with 16 total
        $answers = [
            ['question_id' => $q1->id, 'answer' => 16],
            ['question_id' => $q2->id, 'answer' => 0],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/patient/assessments/{$assessment->uuid}/submit", [
                'answers' => $answers,
            ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('Moderately Severe', $data['severity_level']);

        // db should have stored the same human-friendly label now that the
        // column accepts arbitrary text
        $this->assertDatabaseHas('user_assessment_results', [
            'id' => $data['id'],
            'severity_level' => 'Moderately Severe',
        ]);
    }
}
