<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\TherapySession;
use App\Traits\HasClinicalEthicsGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionReviewController extends BaseController
{
    use HasClinicalEthicsGuard;

    /**
     * Show session details for review
     */
    public function show($uuid): JsonResponse
    {
        $query = TherapySession::with(['patient', 'therapist.user', 'sessionNote'])
            ->where('uuid', $uuid);

        $this->applySessionEthicsGuard($query);

        $session = $query->first();

        if (! $session) {
            return $this->sendError('Session not found or access denied due to ethics policy.');
        }

        return $this->sendResponse($session, 'Session retrieved for review.');
    }

    /**
     * Update session status after admin review
     */
    public function update(Request $request, $uuid): JsonResponse
    {
        $session = TherapySession::where('uuid', $uuid)->first();

        if (! $session) {
            return $this->sendError('Session not found.');
        }

        $request->validate([
            'status' => 'required|in:completed,no_show,ended_early,cancelled',
            'admin_notes' => 'nullable|string',
            'generate_commission' => 'boolean',
        ]);

        $session->status = $request->status;
        $session->notes = $request->admin_notes;
        $session->save();

        if ($request->generate_commission && $request->status === 'completed') {
            // Manually trigger commission calculation if admin overrides
            event(new \App\Events\SessionCompleted($session));
        }

        return $this->sendResponse($session, 'Session review updated.');
    }
}
