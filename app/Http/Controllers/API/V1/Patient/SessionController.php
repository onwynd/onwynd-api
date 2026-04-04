<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Enums\TherapySessionStatus;
use App\Http\Controllers\API\BaseController;
use App\Models\GroupSession;
use App\Models\Review;
use App\Models\TherapySession;
use App\Repositories\Contracts\TherapyRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $sessions = $this->therapyRepository->getPatientSessions($user->id, $request->all());

        return $this->sendResponse($sessions, 'Sessions retrieved successfully.');
    }

    public function show($id)
    {
        $user = Auth::user();
        $session = TherapySession::where('uuid', $id)
            ->orWhere('id', $id)
            ->first();

        if (! $session || $session->patient_id !== $user->id) {
            // Check if it's a group session
            $groupSession = GroupSession::where('uuid', $id)->first();
            if ($groupSession && $groupSession->participants()->where('user_id', $user->id)->exists()) {
                $groupSession->load(['therapist:id,first_name,last_name,profile_photo']);

                return $this->sendResponse($groupSession, 'Group session details retrieved.');
            }

            return $this->sendError('Session not found.', [], 404);
        }

        $session->load(['therapist:id,first_name,last_name,profile_photo', 'sessionNote']);

        return $this->sendResponse($session, 'Session details retrieved successfully.');
    }

    /**
     * GET /api/v1/patient/sessions/upcoming
     * Returns scheduled sessions in the future.
     */
    public function upcoming(Request $request)
    {
        $user = Auth::user();

        $individualSessions = TherapySession::where('patient_id', $user->id)
            ->whereIn('status', TherapySessionStatus::ACTIVE_STATUSES)
            ->where('scheduled_at', '>=', now())
            ->with(['therapist:id,first_name,last_name,profile_photo'])
            ->get()
            ->map(function ($s) {
                $s->session_category = 'individual';

                return $s;
            });

        $groupSessions = GroupSession::whereHas('participants', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now())
            ->with(['therapist:id,first_name,last_name,profile_photo'])
            ->get()
            ->map(function ($s) {
                $s->session_category = 'group';

                return $s;
            });

        $allSessions = $individualSessions->concat($groupSessions)->sortBy('scheduled_at')->values();

        return $this->sendResponse($allSessions, 'Upcoming sessions retrieved successfully.');
    }

    /**
     * GET /api/v1/patient/sessions/past
     * Returns past (completed, cancelled, no_show) sessions.
     */
    public function past(Request $request)
    {
        $user = Auth::user();

        $individualSessions = TherapySession::where('patient_id', $user->id)
            ->whereIn('status', TherapySessionStatus::TERMINAL_STATUSES)
            ->with(['therapist:id,first_name,last_name,profile_photo'])
            ->get()
            ->map(function ($s) {
                $s->session_category = 'individual';

                return $s;
            });

        $groupSessions = GroupSession::whereHas('participants', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->whereIn('status', ['completed', 'cancelled'])
            ->with(['therapist:id,first_name,last_name,profile_photo'])
            ->get()
            ->map(function ($s) {
                $s->session_category = 'group';

                return $s;
            });

        $allSessions = $individualSessions->concat($groupSessions)->sortByDesc('scheduled_at')->values();

        return $this->sendResponse($allSessions, 'Past sessions retrieved successfully.');
    }

    /**
     * GET /api/v1/patient/sessions/history
     * Alias for past sessions with broader filter.
     */
    public function history(Request $request)
    {
        return $this->past($request);
    }

    /**
     * POST /api/v1/patient/sessions/{uuid}/cancel
     */
    public function cancel($uuid, Request $request)
    {
        $user = Auth::user();

        $session = TherapySession::where('uuid', $uuid)
            ->where('patient_id', $user->id)
            ->first();

        if (! $session) {
            return $this->sendError('Session not found.', [], 404);
        }

        if (! in_array($session->status, TherapySessionStatus::CANCELLABLE_STATUSES)) {
            return $this->sendError('Only scheduled or pending sessions can be cancelled.', [], 422);
        }

        // E3: 24-hour cancellation rule
        $scheduledAt = \Carbon\Carbon::parse($session->scheduled_at);
        if ($scheduledAt->diffInHours(now(), false) > -24) {
            return $this->sendError('Cancellations are only allowed up to 24 hours before the session.', [], 422);
        }

        $session->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->input('reason'),
            'cancelled_by' => 'patient',
            'cancelled_at' => now(),
        ]);

        return $this->sendResponse($session, 'Session cancelled successfully.');
    }

    /**
     * PUT /api/v1/patient/sessions/{uuid}/reschedule
     */
    public function reschedule($uuid, Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'scheduled_at' => 'required|date|after:now',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $session = TherapySession::where('uuid', $uuid)
            ->where('patient_id', $user->id)
            ->first();

        if (! $session) {
            return $this->sendError('Session not found.', [], 404);
        }

        if (! in_array($session->status, TherapySessionStatus::CANCELLABLE_STATUSES)) {
            return $this->sendError('Only scheduled or pending sessions can be rescheduled.', [], 422);
        }

        // E3: 24-hour rescheduling rule
        $scheduledAt = \Carbon\Carbon::parse($session->scheduled_at);
        if ($scheduledAt->diffInHours(now(), false) > -24) {
            return $this->sendError('Rescheduling is only allowed up to 24 hours before the session.', [], 422);
        }

        $session->update([
            'scheduled_at' => $request->scheduled_at,
        ]);

        $session->load(['therapist:id,first_name,last_name,profile_photo']);

        return $this->sendResponse($session, 'Session rescheduled successfully.');
    }

    /**
     * POST /api/v1/patient/sessions/{uuid}/join
     * Returns meeting URL for joining the session.
     */
    public function joinMeeting($uuid)
    {
        $user = Auth::user();

        $session = TherapySession::where('uuid', $uuid)
            ->where('patient_id', $user->id)
            ->with('therapist:id,first_name,last_name')
            ->first();

        if (! $session) {
            return $this->sendError('Session not found.', [], 404);
        }

        if (! in_array($session->status, TherapySessionStatus::ACTIVE_STATUSES)) {
            return $this->sendError('Session is not available to join.', [], 422);
        }

        // Update status to ongoing if still scheduled/confirmed/pending
        if (in_array($session->status, TherapySessionStatus::CANCELLABLE_STATUSES)) {
            $session->update(['status' => 'ongoing', 'started_at' => now()]);
        }

        return $this->sendResponse([
            'meeting_url' => $session->meeting_url ?? config('app.frontend_url').'/session/'.$session->uuid,
            'room_id' => $session->room_id,
            'session' => $session,
        ], 'Session join details retrieved successfully.');
    }

    /**
     * POST /api/v1/patient/sessions/{uuid}/end
     */
    public function end($uuid)
    {
        $user = Auth::user();

        $session = TherapySession::where('uuid', $uuid)
            ->where('patient_id', $user->id)
            ->first();

        if (! $session) {
            return $this->sendError('Session not found.', [], 404);
        }

        if (! in_array($session->status, TherapySessionStatus::ACTIVE_STATUSES)) {
            return $this->sendError('Session cannot be ended in its current state.', [], 422);
        }

        $session->update([
            'status' => 'completed',
            'ended_at' => now(),
        ]);

        return $this->sendResponse($session, 'Session ended successfully.');
    }

    /**
     * POST /api/v1/patient/sessions/{uuid}/rate
     */
    public function rate($uuid, Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $session = TherapySession::where('uuid', $uuid)
            ->where('patient_id', $user->id)
            ->first();

        if (! $session) {
            return $this->sendError('Session not found.', [], 404);
        }

        if ($session->status !== 'completed') {
            return $this->sendError('Only completed sessions can be rated.', [], 422);
        }

        // Create or update review
        $review = Review::updateOrCreate(
            [
                'reviewer_id' => $user->id,
                'reviewee_id' => $session->therapist_id,
                'session_id' => $session->id,
            ],
            [
                'rating' => $request->rating,
                'comment' => $request->review,
            ]
        );

        return $this->sendResponse($review, 'Session rated successfully.');
    }

    /**
     * GET /api/v1/patient/sessions/{uuid}/summary
     */
    public function summary($uuid)
    {
        $user = Auth::user();

        $session = TherapySession::where('uuid', $uuid)
            ->where('patient_id', $user->id)
            ->with([
                'therapist:id,uuid,first_name,last_name,profile_photo',
                'sessionNote',
            ])
            ->first();

        if (! $session) {
            return $this->sendError('Session not found.', [], 404);
        }

        $note = $session->sessionNote;

        // Build topics_discussed — use booking_notes as seed if no formal note
        $topicsDiscussed = [];
        if ($note && $note->observations) {
            // Split on newlines or semicolons into individual topics
            $topicsDiscussed = array_values(array_filter(
                array_map('trim', preg_split('/[\n;]+/', $note->observations))
            ));
        }
        if (empty($topicsDiscussed) && $session->booking_notes) {
            $topicsDiscussed = [trim($session->booking_notes)];
        }

        // next_steps — only expose if therapist shared the note
        $nextSteps = [];
        if ($note && $note->is_shared_with_patient && $note->next_steps) {
            $nextSteps = array_values(array_filter(
                array_map('trim', preg_split('/[\n;]+/', $note->next_steps))
            ));
        }

        // therapist_notes — session_summary field if shared
        $therapistNotes = ($note && $note->is_shared_with_patient)
            ? ($note->session_summary ?? null)
            : null;

        // Minimal progress metric — use session rating if available, else neutral 70
        $score = $session->rating ? ($session->rating / 5) * 100 : 70.0;

        $sessionData = $session->toArray();
        // Normalise therapist shape to include uuid (needed for rebook link)
        if ($session->therapist) {
            $sessionData['therapist'] = [
                'uuid'          => $session->therapist->uuid,
                'user'          => [
                    'id'            => $session->therapist->id,
                    'uuid'          => $session->therapist->uuid,
                    'first_name'    => $session->therapist->first_name,
                    'last_name'     => $session->therapist->last_name,
                    'full_name'     => trim("{$session->therapist->first_name} {$session->therapist->last_name}"),
                    'profile_photo' => $session->therapist->profile_photo,
                ],
            ];
        }

        $data = [
            'session'          => $sessionData,
            'topics_discussed' => $topicsDiscussed,
            'key_insights'     => [],
            'next_steps'       => $nextSteps,
            'achievements'     => [],
            'progress_metrics' => [
                'onwynd_score' => round($score, 1),
                'score_change' => 0,
            ],
            'therapist_notes'  => $therapistNotes,
            'recording_url'    => $session->recording_url,
        ];

        return $this->sendResponse($data, 'Session summary retrieved successfully.');
    }
}
