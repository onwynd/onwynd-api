<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TherapistDashboardResource
 *
 * Transforms therapist dashboard data for API response
 */
class TherapistDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'therapist_id' => $this->resource['therapist_id'] ?? null,
            'performance' => $this->resource['performance'] ?? [],
            'patients' => $this->resource['patients'] ?? [],
            'earnings' => $this->resource['earnings'] ?? [],
            'availability' => $this->resource['availability'] ?? [],
            'specializations' => $this->resource['specializations'] ?? [],
            'recent_reviews' => $this->resource['recent_reviews'] ?? [],
            'last_activity' => $this->resource['last_activity'] ?? null,
        ];
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
