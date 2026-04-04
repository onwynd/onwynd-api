<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\API\BaseController;
use App\Models\TherapistPatientInvite;

class PatientInviteAcceptController extends BaseController
{
    /**
     * GET /api/v1/auth/patient-invite/{token}
     * Public — validate a therapist's patient invite before signup.
     * Returns the therapist display info and pre-fills the email.
     */
    public function show(string $token)
    {
        $invite = TherapistPatientInvite::with('therapist:id,first_name,last_name,display_name')
            ->where('token', $token)
            ->first();

        if (! $invite) {
            return $this->sendError('Invite not found.', [], 404);
        }

        if ($invite->isAccepted()) {
            return $this->sendError('This invite has already been accepted.', [], 410);
        }

        if ($invite->isExpired()) {
            return $this->sendError('This invite has expired. Please ask your therapist to resend it.', [], 410);
        }

        $t = $invite->therapist;

        return $this->sendResponse([
            'email'          => $invite->email,
            'expires_at'     => $invite->expires_at->toDateTimeString(),
            'therapist_name' => $t ? trim(($t->display_name ?: $t->first_name) . ' ' . $t->last_name) : null,
            'message'        => $invite->message,
        ], 'Invite is valid.');
    }
}
