<?php

namespace Tests\Feature\AI;

use App\Models\AI\AIDiagnostic;
use App\Models\User;
use App\Services\AI\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_start_diagnostic_session()
    {
        // Mock AI Service
        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('generateResponse')
                ->once()
                ->andReturn('Hello from AI');
        });

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/diagnostic/start');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['session_id', 'status', 'current_stage']]);

        $this->assertDatabaseHas('ai_diagnostics', [
            'user_id' => $this->user->id,
            'current_stage' => 'greeting',
        ]);
    }

    public function test_user_can_chat_with_ai()
    {
        // Mock AI
        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('generateResponse')->andReturn('AI Response');
        });

        // Create session
        $diagnostic = AIDiagnostic::create([
            'user_id' => $this->user->id,
            'session_id' => 'test-session',
            'status' => 'in_progress',
            'current_stage' => 'greeting',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/diagnostic/{$diagnostic->session_id}/chat", [
                'message' => 'I feel sad.',
            ]);

        $response->assertStatus(200);

        // Verify response contains AI message
        $data = $response->json('data');
        $conversations = $data['conversations'];
        $lastMessage = end($conversations);
        $this->assertEquals('AI Response', $lastMessage['content']);
        $this->assertEquals('assistant', $lastMessage['role']);

        // Check DB via model to verify encryption transparency
        $storedConversation = $diagnostic->conversations()->where('role', 'user')->first();
        $this->assertEquals('I feel sad.', $storedConversation->content);
    }

    public function test_escalation_on_high_risk()
    {
        // Mock OpenAI not needed as risk service intercepts before AI call?
        // Actually, my code calls risk service first. If escalated, it returns early.
        // So OpenAI mock shouldn't be called. But to be safe if logic changes:
        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('generateResponse')->never();
        });

        $diagnostic = AIDiagnostic::create([
            'user_id' => $this->user->id,
            'session_id' => 'test-session-risk',
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/diagnostic/{$diagnostic->session_id}/chat", [
                'message' => 'I want to kill myself',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('ai_diagnostics', [
            'id' => $diagnostic->id,
            'status' => 'escalated',
            'risk_level' => 'severe',
        ]);

        $data = $response->json('data');
        $conversations = $data['conversations'];
        $lastMessage = end($conversations);
        $this->assertStringContainsString('emergency services', $lastMessage['content']);
    }
}
