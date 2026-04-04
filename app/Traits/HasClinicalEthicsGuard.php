<?php

namespace App\Traits;

use App\Models\TherapySession;
use Illuminate\Database\Eloquent\Builder;

/**
 * HasClinicalEthicsGuard
 *
 * Enforces the dual-role ethics boundary: a user who holds both
 * clinical_advisor and therapist roles MUST NOT access data belonging
 * to their own patients while acting in their clinical advisor capacity.
 *
 * This is the single most important ethics rule in the codebase.
 * Apply this trait to any controller that queries clinical data
 * (therapy_sessions, distress_flags, crisis events, session notes).
 *
 * Usage — TherapySession queries:
 *   $query = TherapySession::query();
 *   $this->applySessionEthicsGuard($query);
 *
 * Usage — distress_flags / crisis event queries (user_id-based):
 *   $patientIds = $this->getOwnPatientIds();
 *   $query->whereNotIn('user_id', $patientIds);
 *
 * Rule:
 *   WHERE therapist_id != auth()->id()
 *   AND   patient_id  NOT IN (select patient_id from therapy_sessions where therapist_id = auth()->id())
 */
trait HasClinicalEthicsGuard
{
    /**
     * Apply the ethics guard to a TherapySession query.
     * Has no effect if the current user is not a clinical_advisor.
     */
    protected function applySessionEthicsGuard(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user || ! $user->hasRole('clinical_advisor')) {
            return $query;
        }

        $userId = $user->id;

        // Exclude sessions where the CA is the treating therapist
        $query->where('therapist_id', '!=', $userId);

        // Exclude patients who have ever been seen by this CA as a therapist
        $ownPatientIds = $this->getOwnPatientIds($userId);
        if ($ownPatientIds->isNotEmpty()) {
            $query->whereNotIn('patient_id', $ownPatientIds);
        }

        return $query;
    }

    /**
     * Returns a collection of patient_ids belonging to the current user's
     * therapy practice. Used to filter distress/crisis queries that are
     * keyed by user_id rather than patient_id/therapist_id.
     */
    protected function getOwnPatientIds(?int $userId = null): \Illuminate\Support\Collection
    {
        $userId = $userId ?? auth()->id();

        if (! $userId) {
            return collect();
        }

        return TherapySession::where('therapist_id', $userId)
            ->pluck('patient_id')
            ->unique();
    }

    /**
     * Returns true if the current user is a clinical_advisor AND is also
     * a therapist (dual-role). The ethics guard only matters in this case.
     */
    protected function isDualRoleClinicalAdvisor(): bool
    {
        $user = auth()->user();

        return $user
            && $user->hasRole('clinical_advisor')
            && ($user->hasRole('therapist') || $user->therapistProfile()->exists());
    }
}
