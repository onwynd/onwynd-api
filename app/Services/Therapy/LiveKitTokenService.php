<?php

namespace App\Services\Therapy;

use Illuminate\Support\Carbon;

class LiveKitTokenService
{
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function signJwt(array $header, array $payload, string $secret): string
    {
        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public function issueToken(string $userId, string $userName, string $roomName, string $role): array
    {
        $apiKey = env('LIVEKIT_API_KEY');
        $apiSecret = env('LIVEKIT_API_SECRET');
        $host = env('LIVEKIT_HOST');

        if (! $apiKey || ! $apiSecret || ! $host) {
            throw new \RuntimeException('LiveKit not configured');
        }

        $now = Carbon::now('UTC')->timestamp;
        $exp = $now + 3600;

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $permissions = [
            'room' => $roomName,
            'roomCreate' => true,
            'canSubscribe' => true,
            'canPublishData' => true,
        ];

        switch ($role) {
            case 'host':
            case 'moderator':
                $permissions['canPublish'] = true;
                $permissions['roomAdmin'] = true;
                break;
            case 'observer':
                $permissions['canPublish'] = true; // Still needs to publish data for state
                $permissions['canPublishData'] = true;
                $permissions['canPublish'] = false; // Cannot publish audio/video
                break;
            case 'publisher':
            case 'participant':
                $permissions['canPublish'] = true;
                break;
            default: // subscriber
                $permissions['canPublish'] = false;
        }

        $payload = [
            'iss' => $apiKey,
            'exp' => $exp,
            'nbf' => $now,
            'sub' => (string) $userId,
            'name' => $userName,
            'video' => $permissions,
        ];

        $jwt = $this->signJwt($header, $payload, $apiSecret);

        return [
            'token' => $jwt,
            'host' => $host,
            'room' => $roomName,
        ];
    }
}
