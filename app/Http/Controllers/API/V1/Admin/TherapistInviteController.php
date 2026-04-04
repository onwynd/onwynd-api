<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Mail\TherapistInviteEmail;
use App\Models\TherapistInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TherapistInviteController extends BaseController
{
    /**
     * POST /api/v1/admin/therapists/invite
     * Send a personalised invite to a prospective therapist.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        // Prevent duplicate pending invites to the same email
        $existing = TherapistInvite::where('email', $data['email'])
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return $this->sendError(
                'An active invite has already been sent to this email.',
                ['expires_at' => $existing->expires_at->toDateTimeString()],
                409,
            );
        }

        $invite = TherapistInvite::create([
            'email'      => $data['email'],
            'token'      => Str::random(40),
            'invited_by' => $request->user()->id,
            'notes'      => $data['notes'] ?? null,
            'expires_at' => now()->addDays(7),
        ]);

        $inviterName = trim($request->user()->first_name . ' ' . $request->user()->last_name);

        Mail::to($data['email'])->queue(new TherapistInviteEmail($invite, $inviterName));

        return $this->sendResponse([
            'id'         => $invite->id,
            'email'      => $invite->email,
            'expires_at' => $invite->expires_at->toDateTimeString(),
        ], 'Therapist invite sent successfully.');
    }

    /**
     * GET /api/v1/admin/therapists/invites
     * List all therapist invites (for admin view).
     */
    public function index()
    {
        $invites = TherapistInvite::with('invitedBy:id,first_name,last_name,email')
            ->latest()
            ->paginate(20);

        return $this->sendResponse($invites, 'Therapist invites retrieved.');
    }

    /**
     * DELETE /api/v1/admin/therapists/invites/{id}
     * Revoke / cancel a pending invite.
     */
    public function destroy(TherapistInvite $invite)
    {
        if ($invite->isAccepted()) {
            return $this->sendError('Cannot revoke an invite that has already been accepted.', [], 422);
        }

        $invite->delete();

        return $this->sendResponse([], 'Invite revoked.');
    }
}
