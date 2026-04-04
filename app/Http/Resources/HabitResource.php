<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HabitResource extends JsonResource
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
            'frequency' => $this->frequency,
            'target_count' => $this->target_count,
            'reminder_times' => $this->reminder_times,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'category' => $this->category,
            'streak' => $this->streak,
            'longest_streak' => $this->longest_streak,
            'is_archived' => $this->is_archived,
            'logs' => HabitLogResource::collection($this->whenLoaded('logs')),
        ];
    }
}
