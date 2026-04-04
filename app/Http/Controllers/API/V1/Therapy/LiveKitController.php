<?php

namespace App\Http\Controllers\API\V1\Therapy;

use App\Http\Controllers\API\BaseController;
use App\Models\SystemLog;
use App\Models\TherapySession;
use App\Services\Therapy\LiveKitTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LiveKitController extends BaseController
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

    public function token(Request $request)
    {
        $user = $request->user();
        $sessionId = $request->input('session_id');
        $roomName = $request->input('room') ?: ('session-'.$sessionId);
        $role = $request->input('role', 'subscriber');

        try {
            $svc = new LiveKitTokenService;
            $issued = $svc->issueToken((string) $user->id, $user->name ?? ('user-'.$user->id), $roomName, $role);

            return $this->sendResponse($issued, 'LiveKit token issued');
        } catch (\Throwable $e) {
            return $this->sendError('LiveKit not configured', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Validate that the current time is within the session join window.
     *
     * Join window: T-5min to T+90min (Section 7.2, 8.2)
     *   - Before T-5min → too early
     *   - After T+90min → session window has closed
     *
     * Returns null if the time is valid, or an error response array otherwise.
     */
    protected function checkSessionJoinWindow(TherapySession $session): ?array
    {
        if (! $session->scheduled_at) {
            return null; // No scheduled time — allow join (e.g. on-demand sessions)
        }

        $now = Carbon::now('UTC');
        $windowOpen = $session->scheduled_at->copy()->subMinutes(5);
        $windowClose = $session->scheduled_at->copy()->addMinutes(90);

        if ($now->isBefore($windowOpen)) {
            $minutesUntilOpen = (int) $now->diffInMinutes($windowOpen, false) * -1;

            return [
                'message' => 'Your session hasn\'t started yet.',
                'detail' => 'You can join from '.$windowOpen->toIso8601String()." (in {$minutesUntilOpen} minutes).",
                'scheduled_at' => $session->scheduled_at->toIso8601String(),
                'join_from' => $windowOpen->toIso8601String(),
            ];
        }

        if ($now->isAfter($windowClose)) {
            return [
                'message' => 'The join window for this session has closed.',
                'detail' => 'Sessions can only be joined up to 90 minutes after the scheduled start time. Please contact support if you need help.',
                'scheduled_at' => $session->scheduled_at->toIso8601String(),
                'window_closed_at' => $windowClose->toIso8601String(),
            ];
        }

        return null;
    }

    public function join(Request $request, string $uuid)
    {
        try {
            $user = $request->user();
            $session = TherapySession::where('uuid', $uuid)->first();
            if (! $session) {
                return $this->sendError('Session not found', [], 404);
            }

            if ($user->id !== $session->patient_id && $user->id !== $session->therapist_id) {
                return $this->sendError('Unauthorized', [], 403);
            }

            // Enforce session join time window: T-5min to T+90min
            // Therapists are exempt — they may need to prepare the room early
            if ($user->id !== $session->therapist_id) {
                $windowError = $this->checkSessionJoinWindow($session);
                if ($windowError !== null) {
                    return $this->sendError($windowError['message'], $windowError, 403);
                }
            }

            $isTherapist = $user->id === $session->therapist_id;
            $role = $isTherapist ? 'publisher' : 'subscriber';
            $roomName = 'session-'.$session->uuid;

            $svc = new LiveKitTokenService;
            $issued = $svc->issueToken((string) $user->id, $user->name ?? ('user-'.$user->id), $roomName, $role);

            $iceServers = [
                ['urls' => 'stun:stun.l.google.com:19302'],
            ];

            return $this->sendResponse([
                'token' => $issued['token'],
                'url' => $issued['host'],
                'room_name' => $issued['room'],
                'ice_servers' => $iceServers,
                'participant' => [
                    'identity' => (string) $user->id,
                    'name' => $user->name ?? ('user-'.$user->id),
                    'role' => $role,
                ],
                'session' => [
                    'id' => $session->id,
                    'scheduled_at' => optional($session->scheduled_at)->toIso8601String(),
                    'duration' => ($session->started_at && $session->ended_at)
                        ? $session->started_at->diffInMinutes($session->ended_at)
                        : ($session->duration_minutes ?? 0),
                    'status' => $session->status,
                ],
            ], 'LiveKit join payload');
        } catch (\Throwable $e) {
            Log::error('LiveKit join failed', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return $this->sendError('Join failed', ['error' => $e->getMessage()], 500);
        }
    }

    public function end(Request $request, string $uuid)
    {
        $user = $request->user();
        $session = TherapySession::where('uuid', $uuid)->first();
        if (! $session) {
            return $this->sendError('Session not found', [], 404);
        }

        if ($user->id !== $session->patient_id && $user->id !== $session->therapist_id) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $update = ['status' => 'completed'];
        if (! $session->ended_at) {
            $update['ended_at'] = Carbon::now('UTC');
        }
        if ($session->started_at && ($update['ended_at'] ?? $session->ended_at)) {
            $endTime = ($update['ended_at'] ?? $session->ended_at);
            $update['duration_minutes'] = $session->started_at->diffInMinutes($endTime);
        }
        $session->update($update);

        return $this->sendResponse([], 'Session ended');
    }

    /**
     * Record that a participant has voluntarily left the session room.
     * Does NOT end the session — only logs the departure and updates
     * the session's started_at timestamp on first join.
     */
    public function leave(Request $request, string $uuid)
    {
        $user    = $request->user();
        $session = TherapySession::where('uuid', $uuid)->first();

        if (! $session) {
            return $this->sendError('Session not found', [], 404);
        }

        if ($user->id !== $session->patient_id && $user->id !== $session->therapist_id) {
            return $this->sendError('Unauthorized', [], 403);
        }

        Log::info('session_participant_left', [
            'session_uuid' => $uuid,
            'user_id'      => $user->id,
            'left_at'      => Carbon::now('UTC')->toIso8601String(),
        ]);

        return $this->sendResponse(['left' => true], 'Left session');
    }

    /**
     * Return the active/inactive status of a session room and its participant count.
     */
    public function roomStatus(Request $request, string $uuid)
    {
        $user    = $request->user();
        $session = TherapySession::where('uuid', $uuid)->first();

        if (! $session) {
            return $this->sendError('Session not found', [], 404);
        }

        if ($user->id !== $session->patient_id && $user->id !== $session->therapist_id) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $isActive        = in_array($session->status, ['confirmed', 'in_progress'], true);
        $participantCount = 0;

        if (method_exists($session, 'attendees')) {
            $participantCount = $session->attendees()->count();
        }

        return $this->sendResponse([
            'is_active'         => $isActive,
            'participant_count' => $participantCount,
            'status'            => $session->status,
        ], 'Room status retrieved');
    }

    public function participants(Request $request, string $uuid)
    {
        $user = $request->user();
        $session = TherapySession::where('uuid', $uuid)->first();
        if (! $session) {
            return $this->sendError('Session not found', [], 404);
        }

        if ($user->id !== $session->patient_id && $user->id !== $session->therapist_id) {
            return $this->sendError('Unauthorized', [], 403);
        }

        $participants = $session->attendees()->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->pivot->role ?? 'participant',
                'status' => $u->pivot->status ?? 'invited',
                'joined_at' => $u->pivot->joined_at ?? null,
            ];
        });

        return $this->sendResponse($participants, 'Participants listed');
    }

    public function consent(Request $request)
    {
        $user = $request->user();
        $sessionId = $request->input('session_id');
        $consent = $request->boolean('consent', false);
        $ua = $request->header('User-Agent');

        SystemLog::create([
            'level' => 'INFO',
            'message' => 'therapy_session_consent',
            'service' => 'therapy',
            'context' => [
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'consent' => $consent,
                'user_agent' => $ua,
                'timestamp' => Carbon::now('UTC')->toIso8601String(),
            ],
        ]);

        return $this->sendResponse(['consent' => $consent], 'Consent recorded');
    }

    public function joinGroup(Request $request, string $uuid)
    {
        try {
            $user = $request->user();
            $inviteToken = $request->query('invite_token');
            $session = \App\Models\GroupSession::where('uuid', $uuid)->first();
            if (! $session) {
                return $this->sendError('Group Session not found', [], 404);
            }

            $role = 'participant';
            $displayName = 'Guest User';
            $userId = 'guest-'.Str::random(8);

            if ($user) {
                // Check if user is a participant or the therapist
                $isTherapist = $user->id === $session->therapist_id;
                $participant = $session->participants()->where('user_id', $user->id)->first();

                if (! $isTherapist && ! $participant) {
                    return $this->sendError('Unauthorized', [], 403);
                }

                if ($isTherapist) {
                    $role = 'host';
                } elseif ($participant && $participant->pivot->role_in_session === 'observer') {
                    $role = 'observer';
                }

                $displayName = $user->full_name ?? $user->name;
                $userId = (string) $user->id;
            } else {
                // Guest access via invite token
                if (! $inviteToken) {
                    return $this->sendError('Unauthorized. Login required or invite token missing.', [], 401);
                }

                $pivot = DB::table('group_session_participants')
                    ->where('group_session_id', $session->id)
                    ->where('invite_token', $inviteToken)
                    ->first();

                if (! $pivot) {
                    return $this->sendError('Invalid invite token.', [], 404);
                }

                $displayName = $pivot->guest_name ?? 'Guest Participant';
                if ($pivot->role_in_session === 'observer') {
                    $role = 'observer';
                }
            }

            $roomName = $session->livekit_room_name;

            $svc = new LiveKitTokenService;
            $issued = $svc->issueToken($userId, $displayName, $roomName, $role);

            return $this->sendResponse([
                'token' => $issued['token'],
                'url' => $issued['host'],
                'room_name' => $issued['room'],
                'role' => $role,
                'session_uuid' => $session->uuid,
                'display_name' => $displayName,
            ], 'LiveKit token issued for group session');
        } catch (\Throwable $e) {
            return $this->sendError('Error joining group session', ['error' => $e->getMessage()], 500);
        }
    }

    public function getRoomByAppointment(Request $request, string $appointmentId)
    {
        $roomName = 'session-'.$appointmentId;
        $payload = [
            'roomName' => $roomName,
            'appointmentId' => $appointmentId,
            'therapistId' => (string) ($request->user()->id),
            'userId' => (string) ($request->user()->id),
            'createdAt' => Carbon::now('UTC')->toIso8601String(),
            'isActive' => true,
        ];

        return $this->sendResponse($payload, 'Room info');
    }

    public function endRoom(Request $request, string $roomName)
    {
        return $this->sendResponse(['roomName' => $roomName, 'ended' => true], 'Room ended');
    }

    public function participantsByRoom(Request $request, string $roomName)
    {
        return $this->sendResponse(['participants' => []], 'Participants fetched');
    }
}
