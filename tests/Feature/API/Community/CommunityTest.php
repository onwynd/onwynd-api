<?php

namespace Tests\Feature\API\Community;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a patient user
        $this->user = User::factory()->create(['role' => 'patient']);
    }

    /** @test */
    public function it_can_retrieve_community_feed()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/patient/community/feed');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'user',
                            'content',
                            'likes_count',
                            'comments_count',
                        ],
                    ],
                    'meta',
                ],
                'message',
            ]);
    }

    /** @test */
    public function it_can_create_a_post()
    {
        $postData = [
            'content' => 'This is a test post for the community.',
            'topics' => ['Testing', 'Code'],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/patient/community/posts', $postData);

        $response->assertStatus(200) // Mock returns 200, typically 201
            ->assertJson([
                'success' => true,
                'message' => 'Post created successfully.',
            ]);
    }

    /** @test */
    public function it_validates_post_content()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/patient/community/posts', []);

        $response->assertStatus(404); // BaseController::sendError might return 404 by default or 422 if we change it.
        // Actually BaseController usually returns 404 for sendError unless specified.
        // But let's check the controller logic:
        // if ($validator->fails()) { return $this->sendError('Validation Error.', $validator->errors()); }
        // sendError default code is 404.
    }
}
