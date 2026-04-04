<?php

namespace App\Http\Controllers\API\V1\Therapist;

use App\Http\Controllers\API\BaseController;
use App\Repositories\Contracts\TherapyRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SessionController extends BaseController
{
    protected $therapyRepository;

    public function __construct(TherapyRepositoryInterface $therapyRepository)
    {
        $this->therapyRepository = $therapyRepository;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $sessions = $this->therapyRepository->getTherapistSessions($user->id, $request->all());

        return $this->sendResponse($sessions, 'Therapist sessions retrieved successfully.');
    }

    public function show($id)
    {
        $session = $this->therapyRepository->find($id);

        if (! $session || $session->therapist_id !== auth()->id()) {
            return $this->sendError('Session not found.');
        }

        $session->load(['patient:id,first_name,last_name,profile_photo', 'sessionNote']);

        return $this->sendResponse($session, 'Session details retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $session = $this->therapyRepository->find($id);

        if (! $session || $session->therapist_id !== auth()->id()) {
            return $this->sendError('Session not found.');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|string|in:scheduled,completed,cancelled',
            'notes' => 'sometimes|string',
            'diagnosis' => 'nullable|string',
            'prescription' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        if ($request->has('status')) {
            if ($request->status === 'completed') {
                // Minimum duration thresholds
                $minimumMinutes = match ($session->session_type) {
                    '35min' => 20,
                    'audio' => 25,
                    'video' => 25,
                    default => 20,
                };

                $actualMinutes = $session->session_started_at
                    ? now()->diffInMinutes($session->session_started_at)
                    : 0;

                // Guard 1: Patient must have joined
                if (! $session->patient_joined_at) {
                    $session->update([
                        'status' => 'no_show',
                        'actual_duration_minutes' => 0,
                    ]);
                    // Notify admin for review — no commission generated
                    event(new \App\Events\SessionNoShow($session));

                    return $this->sendResponse([
                        'status' => 'no_show',
                        'message' => 'Patient did not join. Session flagged for review.',
                    ], 'Patient did not join.');
                }

                // Guard 2: Minimum duration
                if ($actualMinutes < $minimumMinutes) {
                    $session->update([
                        'status' => 'ended_early',
                        'actual_duration_minutes' => $actualMinutes,
                    ]);
                    // Flag for admin review — no commission generated automatically
                    event(new \App\Events\SessionEndedEarly($session));

                    return $this->sendResponse([
                        'status' => 'ended_early',
                        'duration_minutes' => $actualMinutes,
                        'minimum_required' => $minimumMinutes,
                        'message' => 'Session ended before minimum duration. Flagged for review.',
                    ], 'Session ended early.');
                }

                // Guard 3: Payment confirmed (for paid sessions)
                if ($session->payment_required && $session->payment_status !== 'paid'
                    && $session->payment_status !== 'covered') {
                    return $this->sendError('Cannot complete session — payment not confirmed.', [], 422);
                }

                // All guards passed — safe to complete and pay
                $session->update([
                    'status' => 'completed',
                    'actual_duration_minutes' => $actualMinutes,
                    'completed_at' => now(),
                ]);
                event(new \App\Events\SessionCompleted($session));
            } else {
                $data = ['status' => $request->status];
                $this->therapyRepository->update($id, $data);
            }
            $session->refresh();
        }

        if ($request->has('notes') || $request->has('diagnosis') || $request->has('prescription')) {
            // Mapping request fields to SessionNote model fields
            $this->therapyRepository->updateSessionNote(
                $session->id,
                [
                    'therapist_id' => auth()->id(),
                    'session_summary' => $request->notes,
                    'observations' => $request->diagnosis,
                    'treatment_plan' => $request->prescription,
                    'is_shared_with_patient' => ! $request->boolean('is_private', true),
                ]
            );
        }

        return $this->sendResponse($session->load('sessionNote'), 'Session updated successfully.');
    }

    /**
     * Confirm a therapy session (therapist approval)
     *
     * POST /api/v1/therapist/sessions/{id}/confirm
     */
    public function confirmSession($id): JsonResponse
    {
        $session = $this->therapyRepository->find($id);

        if (! $session || $session->therapist_id !== auth()->id()) {
            return $this->sendError('Session not found.');
        }

        if ($session->status !== 'pending_confirmation') {
            return $this->sendError('Session is not awaiting confirmation.');
        }

        $this->therapyRepository->update($id, [
            'status' => 'scheduled',
        ]);

        $session->refresh();

        // Send confirmation notification to patient
        try {
            $patient = $session->patient;
            \Illuminate\Support\Facades\Mail::to($patient->email)->send(
                new \App\Mail\SessionConfirmationNotification(
                    $patient->full_name,
                    $session->therapist->full_name,
                    \Carbon\Carbon::parse($session->scheduled_at)->toDayDateTimeString()
                )
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Session confirmation email failed', ['error' => $e->getMessage()]);
        }

        return $this->sendResponse($session, 'Session confirmed successfully.');
    }

    /**
     * Notify the patient that the therapist is ready.
     * POST /therapist/notifications/session-ready
     */
    public function notifySessionReady(Request $request): JsonResponse
    {
        $request->validate(['session_uuid' => 'required|string']);
        $session = \App\Models\TherapySession::where('uuid', $request->session_uuid)
            ->where('therapist_id', $request->user()->id)
            ->first();

        if ($session && $session->patient) {
            try {
                \App\Models\Notification::create([
                    'user_id' => $session->patient_id,
                    'type'    => 'session_ready',
                    'title'   => 'Your therapist is ready',
                    'message' => 'Your therapist has joined the session room and is waiting for you.',
                    'data'    => ['session_uuid' => $session->uuid],
                    'is_read' => false,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('session-ready notification failed', ['error' => $e->getMessage()]);
            }
        }

        return $this->sendResponse([], 'Notification sent.');
    }

    /**
     * Notify the patient that the therapist has joined.
     * POST /therapist/notifications/patient-joined
     */
    public function notifyPatientJoined(Request $request): JsonResponse
    {
        $request->validate(['session_uuid' => 'required|string']);
        $session = \App\Models\TherapySession::where('uuid', $request->session_uuid)
            ->where('therapist_id', $request->user()->id)
            ->first();

        if ($session && $session->patient) {
            try {
                \App\Models\Notification::create([
                    'user_id' => $session->patient_id,
                    'type'    => 'therapist_joined',
                    'title'   => 'Therapist has joined',
                    'message' => 'Your therapist has joined the session. Please join when ready.',
                    'data'    => ['session_uuid' => $session->uuid],
                    'is_read' => false,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('patient-joined notification failed', ['error' => $e->getMessage()]);
            }
        }

        return $this->sendResponse([], 'Notification sent.');
    }

    /**
     * Notify the patient that the session is starting soon.
     * POST /therapist/notifications/session-starting
     */
    public function notifySessionStarting(Request $request): JsonResponse
    {
        $request->validate([
            'session_uuid'   => 'required|string',
            'minutes_until'  => 'nullable|integer',
        ]);
        $session = \App\Models\TherapySession::where('uuid', $request->session_uuid)
            ->where('therapist_id', $request->user()->id)
            ->first();

        if ($session && $session->patient) {
            $minutes = $request->integer('minutes_until', 5);
            try {
                \App\Models\Notification::create([
                    'user_id' => $session->patient_id,
                    'type'    => 'session_starting',
                    'title'   => 'Session starting soon',
                    'message' => "Your therapy session starts in {$minutes} minutes.",
                    'data'    => ['session_uuid' => $session->uuid, 'minutes_until' => $minutes],
                    'is_read' => false,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('session-starting notification failed', ['error' => $e->getMessage()]);
            }
        }

        return $this->sendResponse([], 'Notification sent.');
    }
}
