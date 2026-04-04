<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PatientDashboardResource
 *
 * Transforms patient dashboard data for API response
 */
class PatientDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->resource['user_id'] ?? null,
            'wellness' => $this->resource['wellness'] ?? [],
            'engagement' => $this->resource['engagement'] ?? [],
            'therapy' => $this->resource['therapy'] ?? [],
            'goals' => $this->resource['goals'] ?? [],
            'subscription' => $this->resource['subscription'] ?? [],
            'history' => $this->resource['wellness_history'] ?? [],
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
