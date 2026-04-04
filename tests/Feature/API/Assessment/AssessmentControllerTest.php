<?php

namespace Tests\Feature\API\Assessment;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected AssessmentTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Create assessment template with questions
        $this->template = AssessmentTemplate::factory()->create([
            'category' => 'depression',
            'total_score' => 100,
            'is_active' => true,
        ]);

        AssessmentQuestion::factory()->count(10)->create([
            'template_id' => $this->template->id,
            'question_type' => 'scale',
        ]);
    }

    /**
     * Test getting assessment templates
     */
    public function test_get_assessment_templates()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/assessments/templates');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'templates',
                'pagination',
            ],
        ]);
    }

    /**
     * Test getting template questions
     */
    public function test_get_template_questions()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/assessments/templates/{$this->template->id}/questions");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'template',
                'questions',
            ],
        ]);
    }

    /**
     * Test starting an assessment
     */
    public function test_start_assessment()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/assessments/start', [
                'template_id' => $this->template->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'assessment_id',
                'template_id',
                'template_name',
                'question_count',
                'started_at',
            ],
        ]);

        $this->assertDatabaseHas('assessments', [
            'user_id' => $this->user->id,
            'template_id' => $this->template->id,
            'status' => 'in_progress',
        ]);
    }

    /**
     * Test cannot start assessment if already in progress
     */
    public function test_cannot_start_duplicate_assessment()
    {
        Assessment::factory()->create([
            'user_id' => $this->user->id,
            'template_id' => $this->template->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/assessments/start', [
                'template_id' => $this->template->id,
            ]);

        $response->assertStatus(200); // Returns existing assessment
    }

    /**
     * Test submitting an assessment
     */
    public function test_submit_assessment()
    {
        $assessment = Assessment::factory()->create([
            'user_id' => $this->user->id,
            'template_id' => $this->template->id,
            'status' => 'in_progress',
        ]);

        $questions = $this->template->questions()->pluck('id');

        $responses = $questions->map(fn ($id) => [
            'question_id' => $id,
            'response_value' => '3',
            'response_type' => 'scale',
        ])->toArray();

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/assessments/{$assessment->id}/submit", [
                'responses' => $responses,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'status' => 'completed',
        ]);
    }

    /**
     * Test getting user assessments
     */
    public function test_get_user_assessments()
    {
        Assessment::factory()->count(5)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/assessments');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'assessments',
                'pagination',
            ],
        ]);
    }

    /**
     * Test getting current assessment
     */
    public function test_get_current_assessment()
    {
        $assessment = Assessment::factory()->create([
            'user_id' => $this->user->id,
            'template_id' => $this->template->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/assessments/current');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'assessment_id',
                'template',
                'progress',
                'questions',
            ],
        ]);
    }

    /**
     * Test no assessment in progress
     */
    public function test_no_current_assessment()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/assessments/current');

        $response->assertStatus(404);
    }

    /**
     * Test getting assessment details
     */
    public function test_get_assessment_details()
    {
        $assessment = Assessment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/assessments/{$assessment->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'template',
                'status',
                'total_score',
                'responses',
            ],
        ]);
    }

    /**
     * Test unauthorized assessment access
     */
    public function test_unauthorized_assessment_access()
    {
        $otherUser = User::factory()->create();
        $assessment = Assessment::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/assessments/{$assessment->id}");

        $response->assertStatus(403);
    }

    /**
     * Test deleting assessment (in progress only)
     */
    public function test_delete_assessment()
    {
        $assessment = Assessment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/assessments/{$assessment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('assessments', ['id' => $assessment->id]);
    }

    /**
     * Test cannot delete completed assessment
     */
    public function test_cannot_delete_completed_assessment()
    {
        $assessment = Assessment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/assessments/{$assessment->id}");

        $response->assertStatus(400);
    }

    /**
     * Test filtering assessments by status
     */
    public function test_filter_assessments_by_status()
    {
        Assessment::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'in_progress',
        ]);

        Assessment::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/assessments?status=completed');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data.assessments');
    }

    /**
     * Test unauthenticated access
     */
    public function test_unauthenticated_access()
    {
        $response = $this->getJson('/api/v1/assessments/templates');

        $response->assertStatus(401);
    }

    /**
     * Test invalid template
     */
    public function test_invalid_template()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/assessments/start', [
                'template_id' => 999,
            ]);

        $response->assertStatus(400);
    }

    /**
     * Test submitting incomplete assessment
     */
    public function test_submit_incomplete_assessment()
    {
        $assessment = Assessment::factory()->create([
            'user_id' => $this->user->id,
            'template_id' => $this->template->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/assessments/{$assessment->id}/submit", [
                'responses' => [], // Empty responses
            ]);

        // Should handle gracefully - score would be 0
        $response->assertStatus(200);
    }
}
