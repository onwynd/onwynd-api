<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * AdminDashboardResource
 *
 * Transforms admin platform dashboard data for API response
 */
class AdminDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'overview' => $this->resource['overview'] ?? [],
            'therapists' => $this->resource['therapists'] ?? [],
            'institutions' => $this->resource['institutions'] ?? [],
            'revenue' => $this->resource['revenue'] ?? [],
            'growth' => $this->resource['growth'] ?? [],
            'sessions' => $this->resource['sessions'] ?? [],
            'system' => $this->resource['system'] ?? [],
        ];
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'platform_status' => $this->resource['system']['health_status'] ?? 'unknown',
        ];
    }
}
