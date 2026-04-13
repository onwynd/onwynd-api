<?php

namespace App\Http\Controllers\API\V1\Therapist;

use App\Http\Controllers\API\BaseController;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TherapistReferralController extends BaseController
{
    /**
     * GET /api/v1/therapist/referrals
     * List all referrals for the authenticated therapist.
     */
    public function index(Request $request): JsonResponse
    {
        $referrals = Referral::where('therapist_id', $request->user()->id)
            ->with('referredUser') // Ensure this relationship exists on Referral model
            ->latest()
            ->paginate(20);

        return $this->sendResponse($referrals, 'Referrals retrieved successfully.');
    }

    /**
     * PATCH /api/v1/therapist/referrals/{id}
     * Update the status of a referral.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:accepted,declined,pending',
        ]);

        $referral = Referral::find($id);

        if (!$referral) {
            return $this->sendError('Referral not found.', [], 404);
        }

        // Ensure the referral is scoped to the authenticated therapist
        if ($referral->therapist_id !== $request->user()->id) {
            return $this->sendError('Not authorised.', [], 403);
        }

        $referral->update([
            'status' => $request->status,
        ]);

        return $this->sendResponse($referral, 'Referral status updated successfully.');
    }
}
