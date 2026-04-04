<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'review_text' => $this->review_text,
            'is_anonymous' => $this->is_anonymous,
            'is_verified' => $this->is_verified,
            'created_at' => $this->created_at->toIso8601String(),
            'patient' => $this->is_anonymous ? null : [
                'id' => $this->patient_id,
                'name' => $this->patient?->name,
            ],
            'therapist' => [
                'id' => $this->therapist_id,
                'name' => $this->therapist?->name,
            ],
            'session_id' => $this->session_id,
        ];
    }
}
