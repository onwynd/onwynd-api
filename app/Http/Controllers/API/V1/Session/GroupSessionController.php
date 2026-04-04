<?php

namespace App\Http\Controllers\API\V1\Session;

use App\Events\GroupSessionCompleted;
use App\Http\Controllers\Controller;
use App\Mail\CoupleSessionInviteMail;
use App\Mail\GroupSessionInviteMail;
use App\Models\GroupSession;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class GroupSessionController extends Controller
{
    /**
     * List available group sessions
     */
    public function index(Request $request): JsonResponse
    {
        $query = GroupSession::with('therapist')
            ->where('scheduled_at', '>', now()->subHours(2)) // Show slightly past sessions that might be ongoing
            ->where('status', 'scheduled');

        // Filter by session type if provided
        if ($request->has('session_type')) {
            $query->where('session_type', $request->session_type);
        }

        // Only show organization-specific sessions to members of that organization
        $user = Auth::user();
        if ($user && $user->organization_id) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('organization_id')
                    ->orWhere('organization_id', $user->organization_id);
            });
        } else {
            $query->whereNull('organization_id');
        }

        $sessions = $query->orderBy('scheduled_at', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    /**
     * Create a new group session (K.3)
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $request->validate([
            'title'              => 'required|string|max:255',
            'description'        => 'required|string',
            'scheduled_at'       => 'required|date|after:now',
            'duration_minutes'   => 'required|integer|min:15',
            'max_participants'   => 'required|integer|min:2',
            'price_per_seat_kobo'=> 'required|integer|min:0',
            'session_type'       => 'required|in:open,couple,corporate,university',
            'therapist_id'       => 'required_if:session_type,corporate,university|exists:users,id',
            'is_recurring'       => 'boolean',
            'recurrence_rule'    => 'nullable|string',
            'language'           => 'nullable|string|max:10',
            'topic_tags'         => 'nullable|array',
            'is_org_covered'     => 'boolean',
            // Couple-specific fields
            'my_couple_role'     => 'required_if:session_type,couple|in:partner_1,partner_2',
            'partner_email'      => 'required_if:session_type,couple|email',
            'partner_name'       => 'nullable|string|max:255',
        ]);

        // T.2: Corporate/University: Deduct 1 credit from Org pool
        if ($request->is_org_covered || in_array($request->session_type, ['corporate', 'university'])) {
            $org = Organization::find($user->organization_id);
            if (! $org || $org->quota_balance < 1) {
                return response()->json(['success' => false, 'message' => 'Organization has insufficient credits for this group session.'], 403);
            }
            $org->decrement('quota_balance', 1);
        }

        $sessionData = $request->except(['my_couple_role', 'partner_email', 'partner_name']);
        $sessionData['organiser_id'] = $user->id;

        // Map user role to organiser_type
        if ($user->hasRole('hr')) {
            $sessionData['organiser_type'] = 'hr';
        } elseif ($user->hasRole('manager')) {
            $sessionData['organiser_type'] = 'manager';
        } elseif ($user->hasRole('student_affairs')) {
            $sessionData['organiser_type'] = 'student_affairs';
        } else {
            $sessionData['organiser_type'] = 'therapist';
        }

        if ($request->session_type === 'corporate' || $request->session_type === 'university') {
            $sessionData['organization_id'] = $user->organization_id;
        }

        // If therapist is creating, set themselves as the therapist
        if ($user->hasRole('therapist') && ! isset($sessionData['therapist_id'])) {
            $sessionData['therapist_id'] = $user->id;
        }

        $session = GroupSession::create($sessionData);

        // ── Couple session: register creator + auto-invite partner ──────────
        if ($request->session_type === 'couple') {
            $myRole      = $request->my_couple_role;                              // partner_1 or partner_2
            $partnerRole = $myRole === 'partner_1' ? 'partner_2' : 'partner_1';  // opposite, neutral

            // Register the creator as a participant in their role
            $session->participants()->attach($user->id, [
                'invite_status'  => 'accepted',
                'role_in_session'=> 'participant',
                'couple_role'    => $myRole,
                'payment_status' => $session->price_per_seat_kobo > 0 ? 'pending' : 'not_required',
            ]);

            // Auto-create the partner invite record in the pivot table
            $partnerToken = Str::random(64);
            DB::table('group_session_participants')->insert([
                'group_session_id'=> $session->id,
                'user_id'        => null,
                'guest_email'    => $request->partner_email,
                'guest_name'     => $request->partner_name,
                'invite_token'   => $partnerToken,
                'invite_status'  => 'pending',
                'role_in_session'=> 'participant',
                'couple_role'    => $partnerRole,
                'payment_status' => $session->price_per_seat_kobo > 0 ? 'pending' : 'not_required',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // Send the partner a warm couple-specific invite email
            Mail::to($request->partner_email)->send(
                new CoupleSessionInviteMail(
                    session: $session,
                    inviteToken: $partnerToken,
                    partnerName: $request->partner_name,
                    inviterName: $user->name,
                    partnerRole: $partnerRole,
                )
            );
        }
        // ────────────────────────────────────────────────────────────────────

        return response()->json([
            'success' => true,
            'message' => 'Group session created successfully',
            'data'    => $session,
        ], 201);
    }

    /**
     * End a group session (T.1)
     */
    public function end($uuid): JsonResponse
    {
        $user = Auth::user();
        $session = GroupSession::where('uuid', $uuid)->firstOrFail();

        // The assigned therapist can always end their own session.
        // A clinical_advisor may end sessions they do NOT own as treating therapist —
        // this enforces the clinical self-exclusion rule (Section 6.3).
        $isAssignedTherapist = $session->therapist_id === $user->id;
        $isClinicalAdvisorOnOtherSession = $user->hasRole('clinical_advisor') && ! $isAssignedTherapist;

        if (! $isAssignedTherapist && ! $isClinicalAdvisorOnOtherSession) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($session->status === 'completed') {
            return response()->json(['success' => false, 'message' => 'Session already completed'], 400);
        }

        $session->update([
            'status' => 'completed',
            'ended_at' => now(),
        ]);

        event(new GroupSessionCompleted($session));

        return response()->json([
            'success' => true,
            'message' => 'Group session ended successfully',
        ]);
    }

    /**
     * Invite participant (K.4, K.8)
     */
    public function invite(Request $request, $uuid): JsonResponse
    {
        $user = Auth::user();
        $session = GroupSession::where('uuid', $uuid)->firstOrFail();

        // Only organiser or therapist can invite for closed groups
        if ($session->organiser_id !== $user->id && $session->therapist_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'email'       => 'required|email',
            'name'        => 'nullable|string',
            'role'        => 'nullable|in:participant,observer',
            'couple_role' => 'nullable|in:partner_1,partner_2',
        ]);

        $inviteToken = Str::random(64);

        DB::table('group_session_participants')->insert([
            'group_session_id'=> $session->id,
            'user_id'        => null,
            'guest_email'    => $request->email,
            'guest_name'     => $request->name,
            'invite_token'   => $inviteToken,
            'invite_status'  => 'pending',
            'role_in_session'=> $request->role ?? 'participant',
            'couple_role'    => $session->session_type === 'couple' ? ($request->couple_role ?? null) : null,
            'payment_status' => $session->is_org_covered ? 'not_required' : 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Send couple-specific mail for couple sessions, generic otherwise
        if ($session->session_type === 'couple') {
            Mail::to($request->email)->send(
                new CoupleSessionInviteMail(
                    session: $session,
                    inviteToken: $inviteToken,
                    partnerName: $request->name,
                    inviterName: $user->name,
                    partnerRole: $request->couple_role,
                )
            );
        } else {
            Mail::to($request->email)->send(new GroupSessionInviteMail($session, $inviteToken));
        }

        return response()->json([
            'success'      => true,
            'message'      => 'Invite sent successfully',
            'invite_token' => $inviteToken,
        ]);
    }

    /**
     * Join a group session (K.4, K.9)
     */
    public function join($uuid, Request $request): JsonResponse
    {
        $user = Auth::user();
        $session = GroupSession::where('uuid', $uuid)->firstOrFail();
        $inviteToken = $request->input('invite_token');

        // Check if session is full
        if ($session->participants()->count() >= $session->max_participants) {
            return response()->json(['success' => false, 'message' => 'Session is full'], 422);
        }

        // Handle Invite-based joining (Couple, Corporate, University)
        if (in_array($session->session_type, ['couple', 'corporate', 'university'])) {
            if (! $inviteToken) {
                return response()->json(['success' => false, 'message' => 'Invite token required for this session type.'], 403);
            }

            $pivot = DB::table('group_session_participants')
                ->where('group_session_id', $session->id)
                ->where('invite_token', $inviteToken)
                ->first();

            if (! $pivot) {
                return response()->json(['success' => false, 'message' => 'Invalid invite token.'], 404);
            }

            // Update participant record with user_id if logged in
            DB::table('group_session_participants')
                ->where('id', $pivot->id)
                ->update([
                    'user_id' => $user ? $user->id : null,
                    'invite_status' => 'accepted',
                    'updated_at' => now(),
                ]);
        } else {
            // Open group joining
            if (! $user) {
                return response()->json(['success' => false, 'message' => 'Login required for open groups.'], 401);
            }

            if ($session->participants()->where('user_id', $user->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'Already joined.'], 422);
            }

            $session->participants()->attach($user->id, [
                'payment_status' => $session->price_per_seat_kobo > 0 ? 'pending' : 'paid',
                'role_in_session' => 'participant',
                'invite_status' => 'accepted',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully joined group session',
            'data' => [
                'session_uuid' => $session->uuid,
                'payment_required' => ! $session->is_org_covered && $session->price_per_seat_kobo > 0,
            ],
        ]);
    }

    /**
     * Show a specific group session
     */
    public function show($uuid): JsonResponse
    {
        $session = GroupSession::with(['therapist.user', 'organization'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $session,
        ]);
    }

    /**
     * Get summary for a group session (V.3)
     */
    public function summary($uuid, Request $request): JsonResponse
    {
        $user = Auth::user();
        $inviteToken = $request->query('invite_token');

        $session = GroupSession::with(['therapist.user', 'organization'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $isParticipant = false;
        if ($user) {
            $isParticipant = $session->participants()->where('user_id', $user->id)->exists();
        } elseif ($inviteToken) {
            $isParticipant = DB::table('group_session_participants')
                ->where('group_session_id', $session->id)
                ->where('invite_token', $inviteToken)
                ->exists();
        }

        if (! $isParticipant && $session->therapist_id !== ($user?->id ?? 0)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'session' => $session,
                'is_guest' => ! $user,
                'summary_notes' => 'A productive '.$session->session_type.' therapy session focused on collaborative growth.',
                'next_steps' => [
                    'Reflect on the key insights from today.',
                    'Continue practicing the communication techniques discussed.',
                    'Book a follow-up session if needed.',
                ],
            ],
        ]);
    }
}
