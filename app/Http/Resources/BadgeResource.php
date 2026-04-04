<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BadgeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'category' => $this->category,
            'points' => $this->points,
            'awarded_at' => $this->pivot ? $this->pivot->awarded_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
