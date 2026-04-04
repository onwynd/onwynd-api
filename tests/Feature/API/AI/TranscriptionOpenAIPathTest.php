<?php

namespace Tests\Feature\API\AI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TranscriptionOpenAIPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_transcribe_via_openai_driver_with_http_fake()
    {
        $filePath = 'C:\\Users\\mudassar\\Documents\\onwynd\\api\\storage\\09_STOPP.m4a';
        if (! file_exists($filePath)) {
            $this->markTestSkipped('Provided audio file not found: '.$filePath);
        }

        config([
            'services.transcriber.driver' => 'openai',
            'services.openai.api_key' => 'test-key',
        ]);

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'OpenAI path transcript'], 200),
        ]);

        Storage::fake('public');

        $user = User::factory()->create();

        $uploaded = new UploadedFile(
            $filePath,
            '09_STOPP.m4a',
            'audio/m4a',
            null,
            true
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

        $this->assertSame('OpenAI path transcript', $response->json('data.text'));
        $audioPath = str_replace('/storage/', '', $response->json('data.audio_url'));
        Storage::disk('public')->assertExists($audioPath);
    }
}
