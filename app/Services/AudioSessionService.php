<?php

namespace App\Services;

use App\Models\TherapySession;

class AudioSessionService
{
    /**
     * Initiate audio-only session (same as video, but audio stream only)
     */
    public function initiateAudioSession(TherapySession $session)
    {
        $therapistPeerId = "audio_therapist_{$session->id}";
        $userPeerId = "audio_user_{$session->id}";

        return [
            'therapist_peer_id' => $therapistPeerId,
            'user_peer_id' => $userPeerId,
            'session_id' => $session->id,
            'peerjs_server' => env('PEERJS_SERVER', 'localhost'),
            'peerjs_port' => env('PEERJS_PORT', 9000),
            'peerjs_path' => '/peerjs',
            'stun_servers' => [
                'stun:stun.l.google.com:19302',
                'stun:stun1.l.google.com:19302',
                'stun:stun2.l.google.com:19302',
            ],
            'turn_servers' => $this->getTurnServers(), // Optional TURN for NAT traversal
            'connection_type' => 'audio', // Specify audio only
        ];
    }

    /**
     * Get optional TURN servers (if behind firewall, needed for reliability)
     * Can use free TURN servers or self-hosted coturn
     */
    private function getTurnServers()
    {
        return [
            [
                'urls' => env('TURN_SERVER_URL', 'turn:your-turn-server.com'),
                'username' => env('TURN_USERNAME'),
                'credential' => env('TURN_PASSWORD'),
            ],
        ];
    }
}
