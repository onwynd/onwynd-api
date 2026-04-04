<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\API\BaseController;
use App\Models\TherapistInvite;

class TherapistInviteAcceptController extends BaseController
{
    /**
     * GET /api/v1/auth/therapist-invite/{token}
     * Public endpoint — validate a therapist invite token before signup.
     * Returns the invite email so the signup form can pre-fill it.
     */
    public function show(string $token)
    {
        $invite = TherapistInvite::where('token', $token)->first();

        if (! $invite) {
            return $this->sendError('Invite not found.', [], 404);
        }

        if ($invite->isAccepted()) {
            return $this->sendError('This invite has already been accepted.', [], 410);
        }

        if ($invite->isExpired()) {
            return $this->sendError('This invite has expired. Please ask the admin to send a new one.', [], 410);
        }

        return $this->sendResponse([
            'email'      => $invite->email,
            'expires_at' => $invite->expires_at->toDateTimeString(),
        ], 'Invite is valid.');
    }
}
