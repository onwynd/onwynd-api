<?php

namespace App\Http\Controllers\API\V1\Therapy;

use App\Http\Controllers\Controller;
use App\Models\Therapy\VideoSession;
use App\Models\TherapySession;
use App\Services\Therapy\VideoRecordingService;
use App\Services\Therapy\VideoSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VideoSessionController extends Controller
{
    protected $videoService;

    protected $recordingService;

    public function __construct(VideoSessionService $videoService, VideoRecordingService $recordingService)
    {
        $this->videoService = $videoService;
        $this->recordingService = $recordingService;
    }

    /**
     * @OA\PathItem(
     *      path="/api/v1/video-sessions/{session}/initialize",
     *
     *      @OA\Post(
     *           operationId="initializeVideoSession",
     *           tags={"Video Session"},
     *           summary="Initialize a video session",
     *           description="Initialize a video session for a given therapy session",
     *           security={{"bearerAuth":{}}},
     *
     *           @OA\Parameter(
     *               name="session",
     *               description="Therapy Session ID",
     *               required=true,
     *               in="path",
     *
     *               @OA\Schema(
     *                   type="integer"
     *               )
     *           ),
     *
     *           @OA\RequestBody(
     *               required=false,
     *
     *               @OA\JsonContent(
     *
     *                   @OA\Property(property="provider", type="string", example="peerjs", enum={"peerjs", "daily"})
     *               ),
     *           ),
     *
     *           @OA\Response(
     *               response=200,
     *               description="Successful operation",
     *
     *               @OA\JsonContent(
     *
     *                   @OA\Property(property="session", type="object"),
     *                   @OA\Property(property="ice_servers", type="array", @OA\Items(type="object"))
     *               )
     *           ),
     *
     *           @OA\Response(
     *               response=403,
     *               description="Unauthorized"
     *           )
     *      )
     * )
     */
    public function initialize(Request $request, TherapySession $session)
    {
        // Verify user is participant
        if ($request->user()->id !== $session->patient_id && $request->user()->id !== $session->therapist_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Find or create video session
        $videoSession = VideoSession::firstOrCreate(
            ['therapy_session_id' => $session->id],
            [
                'host_id' => $session->therapist_id,
                'participant_id' => $session->patient_id,
                'status' => 'scheduled',
            ]
        );

        // Initialize (Generate IDs or Room URL)
        // Check for force provider in request (e.g. if client detected peerjs failure)
        $provider = $request->input('provider', 'peerjs');

        $videoSession = $this->videoService->initializeSession($videoSession, $provider);

        return response()->json([
            'session' => $videoSession,
            'ice_servers' => $this->videoService->getIceServers(),
        ]);
    }

    /**
     * @OA\PathItem(
     *      path="/api/v1/video-sessions/{videoSession}/fallback",
     *
     *      @OA\Post(
     *           operationId="fallbackVideoSession",
     *           tags={"Video Session"},
     *           summary="Switch to fallback provider (Daily.co)",
     *           description="Switch to Daily.co if PeerJS fails",
     *           security={{"bearerAuth":{}}},
     *
     *           @OA\Parameter(
     *               name="videoSession",
     *               description="Video Session ID",
     *               required=true,
     *               in="path",
     *
     *               @OA\Schema(
     *                   type="string",
     *                   format="uuid"
     *               )
     *           ),
     *
     *           @OA\Response(
     *               response=200,
     *               description="Successful operation",
     *
     *               @OA\JsonContent(
     *
     *                   @OA\Property(property="id", type="integer"),
     *                   @OA\Property(property="provider", type="string", example="daily"),
     *                   @OA\Property(property="daily_room_url", type="string")
     *               )
     *           ),
     *
     *           @OA\Response(
     *               response=500,
     *               description="Fallback failed"
     *           )
     *      )
     * )
     */
    public function fallback(Request $request, VideoSession $videoSession)
    {
        $this->authorizeSession($request, $videoSession);

        try {
            $updatedSession = $this->videoService->createDailyRoom($videoSession);

            return response()->json($updatedSession);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Fallback failed'], 500);
        }
    }

    /**
     * Update session status / Quality Metrics
     */
    public function updateStatus(Request $request, VideoSession $videoSession)
    {
        $this->authorizeSession($request, $videoSession);

        $validated = $request->validate([
            'status' => 'in:active,completed,failed',
            'metrics' => 'nullable|array',
            'disconnect_reason' => 'nullable|string',
        ]);

        $updateData = [];
        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
            if ($validated['status'] === 'active' && ! $videoSession->started_at) {
                $updateData['started_at'] = now();
            }
            if ($validated['status'] === 'completed') {
                $updateData['ended_at'] = now();
                if ($videoSession->started_at) {
                    $updateData['duration_seconds'] = now()->diffInSeconds($videoSession->started_at);
                }
            }
        }

        if (isset($validated['metrics'])) {
            $updateData['quality_metrics'] = $validated['metrics'];
        }

        if (isset($validated['disconnect_reason'])) {
            $updateData['disconnect_reason'] = $validated['disconnect_reason'];
        }

        $videoSession->update($updateData);

        return response()->json($videoSession);
    }

    /**
     * Upload a recording chunk
     */
    public function uploadRecording(Request $request, VideoSession $videoSession)
    {
        $this->authorizeSession($request, $videoSession);

        $request->validate([
            'video' => 'required|file|mimetypes:video/webm,video/mp4',
            'metadata' => 'nullable|json',
        ]);

        $recording = $this->recordingService->storeRecording(
            $videoSession,
            $request->file('video'),
            json_decode($request->metadata, true) ?? []
        );

        return response()->json($recording, 201);
    }

    protected function authorizeSession(Request $request, VideoSession $videoSession)
    {
        Log::info('Authorizing Session', [
            'user_id' => $request->user()->id,
            'host_id' => $videoSession->host_id,
            'participant_id' => $videoSession->participant_id,
            'user_id_type' => gettype($request->user()->id),
            'host_id_type' => gettype($videoSession->host_id),
        ]);

        if ($request->user()->id !== $videoSession->host_id && $request->user()->id !== $videoSession->participant_id) {
            abort(403, 'Unauthorized');
        }
    }
}
