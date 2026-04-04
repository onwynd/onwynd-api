<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TherapySessionResource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'therapist' => new TherapistResource($this->whenLoaded('therapist')),
            'session_type' => $this->session_type,
            'scheduled_date' => $this->scheduled_date,
            'scheduled_time' => $this->scheduled_time,
            'duration_minutes' => $this->duration_minutes,
            'session_fee' => $this->session_fee,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_reference' => $this->payment_reference,
            'notes' => $this->notes,
            'session_notes' => $this->session_notes,
            'next_session_recommendation' => $this->next_session_recommendation,
            'meeting_link' => $this->meeting_link,
            'feedback_submitted' => $this->feedback_submitted,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
