<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'profile_photo' => $this->profile_photo_url,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth,
            'timezone' => $this->timezone,
            'primary_role' => $this->role?->slug,
            'all_roles' => $this->allRoles(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'onboarding_step' => (int) ($this->onboarding_step ?? 0),
            'onboarding_completed_at' => $this->onboarding_completed_at?->toIso8601String(),
            'first_breathing_completed_at' => $this->first_breathing_completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
