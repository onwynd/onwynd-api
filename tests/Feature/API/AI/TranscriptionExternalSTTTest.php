<?php

namespace Tests\Feature\API\AI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TranscriptionExternalSTTTest extends TestCase
{
    use RefreshDatabase;

    public function test_transcribe_via_external_stt_service()
    {
        $filePath = 'C:\\Users\\mudassar\\Documents\\onwynd\\api\\storage\\09_STOPP.m4a';
        if (! file_exists($filePath)) {
            $this->markTestSkipped('Provided audio file not found: '.$filePath);
        }

        // Point the service to the external STT endpoint
        config([
            'services.transcriber.driver' => 'local_whisper',
            'services.transcriber.local_whisper.url' => env('LOCAL_WHISPER_URL', 'https://stt.onwynd.com/asr?output=json'),
            'services.transcriber.verify' => false,
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

        $text = $response->json('data.text');
        $this->assertIsString($text);
        $this->assertNotSame('', trim($text));

        $audioUrl = $response->json('data.audio_url');
        $this->assertIsString($audioUrl);
        $this->assertNotSame('', trim($audioUrl));

        Storage::disk('public')->assertExists(str_replace('/storage/', '', $audioUrl));
    }
}
