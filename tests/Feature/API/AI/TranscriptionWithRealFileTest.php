<?php

namespace Tests\Feature\API\AI;

use App\Models\User;
use App\Services\AI\TranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class TranscriptionWithRealFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_transcribe_with_provided_m4a_file()
    {
        $filePath = 'C:\\Users\\mudassar\\Documents\\onwynd\\api\\storage\\09_STOPP.m4a';

        if (! file_exists($filePath)) {
            $this->markTestSkipped('Provided audio file not found: '.$filePath);
        }

        Storage::fake('public');

        $user = User::factory()->create();

        // Mock the transcription provider to avoid external API dependency
        $mock = Mockery::mock(TranscriptionService::class);
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldReceive('transcribe')->andReturn(['text' => 'Transcribed from provided file']);
        $this->app->instance(TranscriptionService::class, $mock);

        $uploaded = new UploadedFile(
            $filePath,
            '09_STOPP.m4a',
            'audio/m4a',
            null,
            true // test mode
        );

        $response = $this->actingAs($user)->post('/api/v1/ai/transcribe', [
            'audio_file' => $uploaded,
            'duration_seconds' => 95,
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
        $this->assertEquals('Transcribed from provided file', $data['text']);
        $this->assertNotEmpty($data['audio_url']);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $data['audio_url']));
    }
}
