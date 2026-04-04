<?php

namespace Tests\Feature\API\AI;

use App\Models\User;
use App\Services\AI\TranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class TranscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_transcribe_audio_returns_text_and_audio_url()
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $mock = Mockery::mock(TranscriptionService::class);
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldReceive('transcribe')->andReturn(['text' => 'hello world']);
        $this->app->instance(TranscriptionService::class, $mock);

        $file = UploadedFile::fake()->create('voice.mp3', 100, 'audio/mpeg');

        $response = $this->actingAs($user)->post('/api/v1/ai/transcribe', [
            'audio_file' => $file,
            'duration_seconds' => 123,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'text',
                'audio_url',
                'duration_seconds',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals('hello world', $data['text']);
        $this->assertNotEmpty($data['audio_url']);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $data['audio_url']));
    }
}
