<?php

namespace App\Services\Therapy;

use App\Models\Therapy\VideoSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VideoSessionService
{
    protected LiveKitTokenService $tokenService;

    public function __construct(LiveKitTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    public function initializeSession(VideoSession $videoSession): VideoSession
    {
        $videoSession->update([
            'provider' => 'livekit',
            'status' => 'scheduled',
        ]);

        return $videoSession;
    }

    public function handleTimeout(VideoSession $session): void
    {
        if ($session->status !== 'completed') {
            $session->update([
                'status' => 'failed',
                'disconnect_reason' => 'timeout',
                'ended_at' => now(),
            ]);
        }
    }

    /**
     * Create a LiveKit room via the REST API.
     * LiveKit auto-creates rooms on first join, but pre-creating ensures
     * the room config (empty timeout, max participants) is set correctly.
     *
     * Returns ['name' => string] on success.
     */
    public function createRoom(VideoSession $session): array
    {
        $roomName = 'session-' . $session->id;

        $host = env('LIVEKIT_HOST');
        $apiKey = env('LIVEKIT_API_KEY');
        $apiSecret = env('LIVEKIT_API_SECRET');

        if (! $host || ! $apiKey || ! $apiSecret) {
            Log::warning('LiveKit not configured — skipping room creation', ['session_id' => $session->id]);
            return ['name' => $roomName];
        }

        try {
            // Issue an admin token (no room restriction, roomCreate permission)
            $adminToken = $this->tokenService->issueToken(
                userId: 'server',
                userName: 'Onwynd Server',
                roomName: $roomName,
                role: 'host'
            );

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $adminToken['token'],
                'Content-Type' => 'application/json',
            ])->post(rtrim($host, '/') . '/twirp/livekit.RoomService/CreateRoom', [
                'name' => $roomName,
                'empty_timeout' => 600,   // Delete room 10 min after everyone leaves
                'max_participants' => 4,  // Therapist + patient + optional observers
            ]);

            if ($response->successful()) {
                Log::info('LiveKit room created', ['room' => $roomName, 'session_id' => $session->id]);
            } else {
                Log::warning('LiveKit room creation failed (non-fatal)', [
                    'room' => $roomName,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('LiveKit room creation exception (non-fatal)', [
                'room' => $roomName,
                'error' => $e->getMessage(),
            ]);
        }

        return ['name' => $roomName];
    }

    /**
     * Generate LiveKit tokens for therapist and patient.
     */
    public function generateTokens(VideoSession $session): array
    {
        $roomName = $session->room_name ?? ('session-' . $session->id);

        $therapistToken = $this->tokenService->issueToken(
            userId: 'therapist-' . $session->host_id,
            userName: $session->host?->full_name ?? ('Therapist #' . $session->host_id),
            roomName: $roomName,
            role: 'host'
        );

        $patientToken = $this->tokenService->issueToken(
            userId: 'patient-' . $session->participant_id,
            userName: $session->participant?->full_name ?? ('Patient #' . $session->participant_id),
            roomName: $roomName,
            role: 'participant'
        );

        return [
            'therapist_token' => $therapistToken['token'],
            'patient_token' => $patientToken['token'],
        ];
    }

    /**
     * Prepare a session 30 minutes before start:
     * 1. Create LiveKit room
     * 2. Generate tokens
     * 3. Persist room_name + tokens on VideoSession
     * 4. Send join-link emails to both parties
     */
    public function prepareSession(VideoSession $session): void
    {
        if ($session->status === 'prepared' || $session->prepared_at !== null) {
            Log::info('VideoSession already prepared — skipping', ['id' => $session->id]);
            return;
        }

        $room = $this->createRoom($session);
        $tokens = $this->generateTokens($session->fresh()->loadMissing(['host', 'participant']));

        $session->update([
            'room_name' => $room['name'],
            'therapist_token' => $tokens['therapist_token'],
            'patient_token' => $tokens['patient_token'],
            'status' => 'prepared',
            'prepared_at' => now(),
        ]);

        $this->sendJoinLinks($session->fresh()->loadMissing(['host', 'participant', 'therapySession']));

        Log::info('VideoSession prepared', ['id' => $session->id, 'room' => $room['name']]);
    }

    protected function sendJoinLinks(VideoSession $session): void
    {
        $therapySession = $session->therapySession;
        $host = env('APP_URL', 'https://app.onwynd.com');
        $sessionUuid = $therapySession?->uuid ?? $session->id;
        $joinUrl = $host . '/session/' . $sessionUuid . '/join';

        $scheduledAt = optional($therapySession?->scheduled_at)->format('D, M j \a\t g:i A T');

        // Email to therapist
        if ($session->host?->email) {
            try {
                Mail::raw(
                    "Your therapy session is starting in 30 minutes.\n\n"
                    . "Patient: " . ($session->participant?->full_name ?? 'Your patient') . "\n"
                    . "Time: " . ($scheduledAt ?? 'Scheduled') . "\n\n"
                    . "Join here: " . $joinUrl . "\n\n"
                    . "— Onwynd",
                    fn ($m) => $m->to($session->host->email)->subject('Your session starts in 30 minutes')
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to send join email to therapist', ['error' => $e->getMessage()]);
            }
        }

        // Email to patient
        if ($session->participant?->email) {
            try {
                Mail::raw(
                    "Your therapy session is starting in 30 minutes.\n\n"
                    . "Therapist: " . ($session->host?->full_name ?? 'Your therapist') . "\n"
                    . "Time: " . ($scheduledAt ?? 'Scheduled') . "\n\n"
                    . "Join here: " . $joinUrl . "\n\n"
                    . "— Onwynd",
                    fn ($m) => $m->to($session->participant->email)->subject('Your session starts in 30 minutes')
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to send join email to patient', ['error' => $e->getMessage()]);
            }
        }
    }
}
