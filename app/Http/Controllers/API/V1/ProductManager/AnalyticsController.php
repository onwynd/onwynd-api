<?php

namespace App\Http\Controllers\API\V1\ProductManager;

use App\Http\Controllers\API\BaseController;
use App\Models\ProductFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends BaseController
{
    /**
     * Get product analytics metrics.
     */
    public function metrics(Request $request)
    {
        $metrics = [
            'features_by_status' => ProductFeature::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status'),

            'features_by_priority' => ProductFeature::select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority'),

            'features_by_quarter' => ProductFeature::select('quarter', DB::raw('count(*) as count'))
                ->whereNotNull('quarter')
                ->groupBy('quarter')
                ->pluck('count', 'quarter'),

            'completion_rate' => $this->calculateCompletionRate(),
        ];

        return $this->sendResponse($metrics, 'Product analytics retrieved successfully.');
    }

    private function calculateCompletionRate()
    {
        $total = ProductFeature::count();
        if ($total == 0) {
            return 0;
        }

        $completed = ProductFeature::whereIn('status', ['completed', 'released'])->count();

        return round(($completed / $total) * 100, 1);
    }

    /**
     * Get feature velocity (features completed over time).
     */
    public function velocity(Request $request)
    {
        // Group completed features by month/week
        $velocity = ProductFeature::whereIn('status', ['completed', 'released'])
            ->select(DB::raw('DATE_FORMAT(updated_at, "%Y-%m") as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return $this->sendResponse($velocity, 'Product velocity retrieved successfully.');
    }
}
