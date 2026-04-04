<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;

class OnboardingController extends BaseController
{
    /**
     * GET /patient/onboarding/first-login
     * Returns the user's first-login / onboarding status.
     */
    public function firstLoginStatus(Request $request)
    {
        $user = $request->user();

        return $this->sendResponse([
            'onboarding_step' => (int) ($user->onboarding_step ?? 0),
            'onboarding_completed_at' => $user->onboarding_completed_at?->toIso8601String(),
            'first_breathing_completed_at' => $user->first_breathing_completed_at?->toIso8601String(),
            'is_complete' => $user->onboarding_completed_at !== null,
        ], 'Onboarding status retrieved.');
    }

    /**
     * POST /patient/onboarding/first-login/complete
     * Legacy alias — marks onboarding complete (same as /complete below).
     */
    public function firstLoginComplete(Request $request)
    {
        return $this->complete($request);
    }

    /**
     * POST /patient/onboarding/complete
     * Marks the patient profile onboarding as fully complete.
     */
    public function complete(Request $request)
    {
        $user = $request->user();

        $user->update([
            'onboarding_completed_at' => now(),
            'onboarding_step' => 99,
        ]);

        return $this->sendResponse([
            'onboarding_completed_at' => $user->fresh()->onboarding_completed_at->toIso8601String(),
        ], 'Onboarding completed successfully.');
    }

    /**
     * POST /patient/onboarding/breathing-complete
     * Marks that the user has completed their first breathing session
     * and advances onboarding_step to 1.
     */
    public function breathingComplete(Request $request)
    {
        $user = $request->user();

        if (! $user->first_breathing_completed_at) {
            $user->update([
                'first_breathing_completed_at' => now(),
                'onboarding_step' => max(1, (int) ($user->onboarding_step ?? 0)),
            ]);
        }

        return $this->sendResponse([
            'first_breathing_completed_at' => $user->fresh()->first_breathing_completed_at->toIso8601String(),
        ], 'Breathing session recorded.');
    }
}
