<?php

namespace App\Http\Controllers\API\V1\Chat;

use App\Events\Call\CallSignal;
use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CallController extends BaseController
{
    /**
     * Initiate a call
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'type' => 'required|in:audio,video',
        ]);

        $callId = (string) Str::uuid();
        $channelId = 'call_'.$callId; // Unique channel for this call

        // In a real app, you'd save this Call model to DB to track duration, status, etc.
        // Call::create([...]);

        // Notify recipient (e.g., via Push Notification or existing presence channel)
        // For now, we return the signaling channel details

        return $this->sendResponse([
            'call_id' => $callId,
            'channel_id' => $channelId,
            'initiator_id' => $request->user()->id,
            'recipient_id' => $request->recipient_id,
            'type' => $request->type,
            'ice_servers' => [
                ['urls' => 'stun:stun.l.google.com:19302'], // Default public STUN
            ],
        ], 'Call initiated successfully.');
    }

    /**
     * Handle WebRTC Signaling (Offer, Answer, ICE Candidates)
     * This acts as a relay if P2P fails or for initial handshake via WebSocket
     */
    public function signal(Request $request, $callId)
    {
        $request->validate([
            'type' => 'required|string', // offer, answer, candidate, bye
            'payload' => 'required',
            'recipient_id' => 'required|exists:users,id',
        ]);

        // Broadcast the signal to the specific private channel for this call
        // The frontend should subscribe to 'private-call.{callId}'

        // Note: For client-events to work directly in Pusher/Reverb without hitting backend,
        // you need to enable 'client events' in dashboard.
        // If not, we proxy via this endpoint.

        // We broadcast an event that the other party listens to
        // broadcast(new CallSignal($request->all(), $callId))->toOthers();

        // Simpler approach: Just return success, assuming frontend uses
        // 'client-signal' events directly on the channel if permitted.
        // If strict backend control is needed:

        broadcast(new CallSignal([
            'type' => $request->type,
            'payload' => $request->payload,
            'sender_id' => $request->user()->id,
        ], $callId))->toOthers();

        return $this->sendResponse([], 'Signal sent.');
    }
}
