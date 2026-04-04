<?php

namespace Tests\Feature\API\Chat;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;

    protected User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
    }

    /**
     * Test getting conversations
     */
    public function test_get_conversations()
    {
        Conversation::factory()->count(3)->create([
            'initiator_id' => $this->user1->id,
            'recipient_id' => $this->user2->id,
        ]);

        $response = $this->actingAs($this->user1)
            ->getJson('/api/v1/conversations');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'conversations',
                'pagination',
            ],
        ]);
    }

    /**
     * Test creating a conversation
     */
    public function test_create_conversation()
    {
        $response = $this->actingAs($this->user1)
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->user2->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => ['conversation_id'],
        ]);
    }

    /**
     * Test cannot create conversation with self
     */
    public function test_cannot_create_conversation_with_self()
    {
        $response = $this->actingAs($this->user1)
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->user1->id,
            ]);

        $response->assertStatus(400);
    }

    /**
     * Test getting conversation messages
     */
    public function test_get_conversation()
    {
        $conversation = Conversation::factory()->create([
            'initiator_id' => $this->user1->id,
            'recipient_id' => $this->user2->id,
        ]);

        Message::factory()->count(5)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->user1->id,
        ]);

        $response = $this->actingAs($this->user1)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'conversation_id',
                'messages',
                'pagination',
            ],
        ]);
    }

    /**
     * Test sending a message
     */
    public function test_send_message()
    {
        $conversation = Conversation::factory()->create([
            'initiator_id' => $this->user1->id,
            'recipient_id' => $this->user2->id,
        ]);

        $response = $this->actingAs($this->user1)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'content' => 'Hello, how are you?',
                'message_type' => 'text',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'message_id',
                'conversation_id',
                'content',
                'created_at',
            ],
        ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $this->user1->id,
            'content' => 'Hello, how are you?',
        ]);
    }

    /**
     * Test cannot send message to blocked conversation
     */
    public function test_cannot_send_message_to_blocked()
    {
        $conversation = Conversation::factory()->create([
            'initiator_id' => $this->user1->id,
            'recipient_id' => $this->user2->id,
            'blocked_by_recipient' => true,
        ]);

        $response = $this->actingAs($this->user1)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'content' => 'Hello',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test deleting a message
     */
    public function test_delete_message()
    {
        $message = Message::factory()->create([
            'sender_id' => $this->user1->id,
            'created_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($this->user1)
            ->deleteJson("/api/v1/messages/{$message->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    /**
     * Test cannot delete message after 5 minutes
     */
    public function test_cannot_delete_old_message()
    {
        $message = Message::factory()->create([
            'sender_id' => $this->user1->id,
            'created_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->user1)
            ->deleteJson("/api/v1/messages/{$message->id}");

        $response->assertStatus(400);
    }

    /**
     * Test marking messages as read
     */
    public function test_mark_as_read()
    {
        $conversation = Conversation::factory()->create([
            'initiator_id' => $this->user1->id,
            'recipient_id' => $this->user2->id,
        ]);

        Message::factory()->count(3)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->user1->id,
        ]);

        $response = $this->actingAs($this->user2)
            ->postJson("/api/v1/conversations/{$conversation->id}/mark-read");

        $response->assertStatus(200);
    }

    /**
     * Test getting unread count
     */
    public function test_get_unread_count()
    {
        $conversation = Conversation::factory()->create([
            'initiator_id' => $this->user1->id,
            'recipient_id' => $this->user2->id,
            'unread_recipient_count' => 5,
        ]);

        $response = $this->actingAs($this->user2)
            ->getJson('/api/v1/messages/unread/count');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => ['unread_count'],
        ]);
    }

    /**
     * Test searching messages
     */
    public function test_search_messages()
    {
        $conversation = Conversation::factory()->create([
            'initiator_id' => $this->user1->id,
            'recipient_id' => $this->user2->id,
        ]);

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'content' => 'Python programming tutorial',
        ]);

        $response = $this->actingAs($this->user1)
            ->getJson('/api/v1/messages/search?q=Python');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'results',
                'pagination',
            ],
        ]);
    }

    /**
     * Test blocking user
     */
    public function test_block_user()
    {
        $conversation = Conversation::factory()->create([
            'initiator_id' => $this->user1->id,
            'recipient_id' => $this->user2->id,
        ]);

        $response = $this->actingAs($this->user1)
            ->postJson("/api/v1/conversations/{$conversation->id}/block");

        $response->assertStatus(200);
        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'blocked_by_initiator' => true,
        ]);
    }

    /**
     * Test unblocking user
     */
    public function test_unblock_user()
    {
        $conversation = Conversation::factory()->create([
            'initiator_id' => $this->user1->id,
            'recipient_id' => $this->user2->id,
            'blocked_by_initiator' => true,
        ]);

        $response = $this->actingAs($this->user1)
            ->postJson("/api/v1/conversations/{$conversation->id}/unblock");

        $response->assertStatus(200);
        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'blocked_by_initiator' => false,
        ]);
    }

    /**
     * Test unauthorized conversation access
     */
    public function test_unauthorized_conversation_access()
    {
        $otherUser = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'initiator_id' => $this->user1->id,
            'recipient_id' => $this->user2->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages");

        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated access
     */
    public function test_unauthenticated_access()
    {
        $response = $this->getJson('/api/v1/conversations');

        $response->assertStatus(401);
    }

    /**
     * Test message with empty content
     */
    public function test_send_message_empty_content()
    {
        $conversation = Conversation::factory()->create([
            'initiator_id' => $this->user1->id,
            'recipient_id' => $this->user2->id,
        ]);

        $response = $this->actingAs($this->user1)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'content' => '',
            ]);

        $response->assertStatus(400);
    }
}
