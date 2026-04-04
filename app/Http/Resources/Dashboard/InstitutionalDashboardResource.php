<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * InstitutionalDashboardResource
 *
 * Transforms institutional dashboard data for API response
 */
class InstitutionalDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'institution_id' => $this->resource['institution_id'] ?? null,
            'overview' => $this->resource['overview'] ?? [],
            'sessions' => $this->resource['sessions'] ?? [],
            'wellness' => $this->resource['wellness'] ?? [],
            'financial' => $this->resource['financial'] ?? [],
            'contract' => $this->resource['contract'] ?? [],
            'risk' => $this->resource['risk'] ?? [],
            'satisfaction' => $this->resource['satisfaction'] ?? [],
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
