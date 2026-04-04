<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TherapistResource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'specialization' => $this->specialization,
            'qualification' => $this->qualification,
            'experience_years' => $this->years_of_experience,
            'bio' => $this->bio,
            'hourly_rate' => $this->hourly_rate,
            'currency' => 'NGN',
            'avatar_url' => $this->avatar_url,
            'certificate_url' => $this->certificate_url,
            'license_number' => $this->license_number,
            'license_expiry' => $this->license_expiry,
            'is_verified' => $this->is_verified,
            'status' => $this->status,
            'rating' => $this->whenLoaded('ratings', function () {
                return $this->ratings()->avg('rating') ?? 0;
            }),
            'total_reviews' => $this->whenLoaded('ratings', function () {
                return $this->ratings()->count();
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
