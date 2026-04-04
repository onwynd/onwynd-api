<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveKitTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_livekit_token_for_verified_user()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/api/v1/therapy/video/token', [
            'session_id' => 123,
            'room' => 'session-123',
            'role' => 'publisher',
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('host', $data);
        $this->assertArrayHasKey('room', $data);
        $this->assertNotEmpty($data['token']);
        $this->assertNotEmpty($data['host']);
        $this->assertSame('session-123', $data['room']);
    }
}
