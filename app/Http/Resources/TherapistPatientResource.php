<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Therapist-facing patient view.
 * Exposes only the display_name (not full surname) and hides email/phone
 * to protect patient identity until both parties consent to share.
 */
class TherapistPatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $patient = $this->patient;
        $preferences = $patient?->preferences ?? [];
        $shareIdentity = (bool) data_get($preferences, 'share_identity', false);

        return [
            'id'              => $this->id,
            'uuid'            => $this->uuid ?? (string) $this->id,
            // Show display_name first; fall back to first name only — never surname without consent
            'first_name'      => $this->display_name ?: $this->first_name,
            'display_name'    => $this->display_name ?: $this->first_name,
            'last_name'       => $shareIdentity ? $this->last_name : null,
            // Email & phone only exposed when patient has opted-in to identity sharing
            'email'           => $shareIdentity ? $this->email : null,
            'email_protected' => ! $shareIdentity,
            'phone'           => $shareIdentity ? $this->phone : null,
            'gender'          => $this->gender,
            'date_of_birth'   => $this->date_of_birth,
            'language'        => $this->preferred_language ?? $this->language,
            'timezone'        => $this->timezone,
            // Flat convenience fields for table display
            'profile_photo'   => $this->profile_photo_url,
            'avatar_url'      => $this->profile_photo_url,
            'department'      => $patient?->department,
            'status'          => $patient?->status ?? 'active',
            'created_at'      => $this->created_at?->toIso8601String(),
            'mental_health_goals' => $this->mental_health_goals ?? [],
            'onboarding_completed_at' => $this->onboarding_completed_at,
            'patient'         => $patient ? [
                'department'        => $patient->department,
                'status'            => $patient->status,
                'medical_history'   => $shareIdentity ? ($patient->medical_history ?? []) : null,
                'emergency_contact' => $shareIdentity ? ($patient->emergency_contact ?? []) : null,
                'preferences'       => $preferences,
            ] : null,
            'identity_shared' => $shareIdentity,
        ];
    }
}
