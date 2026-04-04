<?php

namespace Tests\Unit\Repositories;

use App\Models\AI\AIDiagnostic;
use App\Models\User;
use App\Repositories\Eloquent\AIEloquentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new AIEloquentRepository(new AIDiagnostic);
    }

    public function test_it_can_create_diagnostic_session()
    {
        $user = User::factory()->create();

        $diagnostic = $this->repository->createDiagnosticSession($user->id);

        $this->assertInstanceOf(AIDiagnostic::class, $diagnostic);
        $this->assertEquals($user->id, $diagnostic->user_id);
        $this->assertEquals('greeting', $diagnostic->current_stage);
        $this->assertNotNull($diagnostic->session_id);
    }

    public function test_it_can_add_message()
    {
        $user = User::factory()->create();
        $diagnostic = $this->repository->createDiagnosticSession($user->id);

        $message = $this->repository->addMessage(
            $diagnostic->id,
            'user',
            'Hello',
            ['tokens' => 10]
        );

        $this->assertEquals('user', $message->role);
        $this->assertEquals('Hello', $message->content);
        $this->assertEquals($diagnostic->id, $message->ai_diagnostic_id);
        $this->assertEquals(10, $message->metadata['tokens']);
    }

    public function test_it_can_get_diagnostic_with_history()
    {
        $user = User::factory()->create();
        $diagnostic = $this->repository->createDiagnosticSession($user->id);

        $this->repository->addMessage($diagnostic->id, 'user', 'Hi', []);
        $this->repository->addMessage($diagnostic->id, 'assistant', 'Hello', []);

        $result = $this->repository->getDiagnosticWithHistory($diagnostic->id, $user->id);

        $this->assertNotNull($result);
        $this->assertEquals($diagnostic->id, $result->id);
        $this->assertCount(2, $result->conversations);
        $this->assertEquals('Hi', $result->conversations[0]->content);
    }
}
