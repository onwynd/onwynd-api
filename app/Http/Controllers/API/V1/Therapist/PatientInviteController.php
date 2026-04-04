<?php

namespace App\Http\Controllers\API\V1\Therapist;

use App\Http\Controllers\API\BaseController;
use App\Mail\TherapistPatientInviteEmail;
use App\Models\TherapistPatientInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PatientInviteController extends BaseController
{
    /**
     * POST /api/v1/therapist/patient-invites
     * Send a personalised invite to a prospective patient.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'email'   => 'required|email|max:255',
            'message' => 'nullable|string|max:600',
        ]);

        // Block duplicate pending invites from this therapist to the same email
        $existing = TherapistPatientInvite::where('email', $data['email'])
            ->where('therapist_id', $request->user()->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return $this->sendError(
                'You already have an active invite pending for this email.',
                ['expires_at' => $existing->expires_at->toDateTimeString()],
                409,
            );
        }

        $invite = TherapistPatientInvite::create([
            'email'        => $data['email'],
            'token'        => Str::random(40),
            'therapist_id' => $request->user()->id,
            'message'      => $data['message'] ?? null,
            'expires_at'   => now()->addDays(14),
        ]);

        Mail::to($data['email'])->queue(new TherapistPatientInviteEmail($invite));

        return $this->sendResponse([
            'id'         => $invite->id,
            'email'      => $invite->email,
            'expires_at' => $invite->expires_at->toDateTimeString(),
        ], 'Patient invite sent successfully.');
    }

    /**
     * GET /api/v1/therapist/patient-invites
     * List this therapist's sent invites.
     */
    public function index(Request $request)
    {
        $invites = TherapistPatientInvite::where('therapist_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return $this->sendResponse($invites, 'Patient invites retrieved.');
    }

    /**
     * DELETE /api/v1/therapist/patient-invites/{invite}
     * Revoke a pending invite.
     */
    public function destroy(Request $request, TherapistPatientInvite $patientInvite)
    {
        if ($patientInvite->therapist_id !== $request->user()->id) {
            return $this->sendError('Not authorised.', [], 403);
        }

        if ($patientInvite->isAccepted()) {
            return $this->sendError('Cannot revoke an invite that has already been accepted.', [], 422);
        }

        $patientInvite->delete();

        return $this->sendResponse([], 'Invite revoked.');
    }
}
