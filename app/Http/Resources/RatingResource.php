<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RatingResource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'therapist_id' => $this->therapist_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'rating' => $this->rating,
            'feedback' => $this->feedback,
            'session_id' => $this->session_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
