<?php

namespace App\Http\Controllers\API\V1\Product;

use App\Http\Controllers\API\BaseController;

class AnalyticsController extends BaseController
{
    public function metrics()
    {
        $metrics = [
            'daily_active_users' => [
                'value' => 1250,
                'trend' => '+5%',
                'history' => [1000, 1100, 1150, 1200, 1250],
            ],
            'retention_rate' => [
                'value' => '68%',
                'trend' => '+2%',
                'history' => [60, 62, 65, 66, 68],
            ],
            'avg_session_duration' => [
                'value' => '12m 30s',
                'trend' => '-1%',
                'history' => [13, 12.8, 12.5, 12.6, 12.5],
            ],
            'feature_adoption' => [
                ['name' => 'Journaling', 'value' => 85],
                ['name' => 'Meditation', 'value' => 60],
                ['name' => 'Community', 'value' => 45],
            ],
        ];

        return $this->sendResponse($metrics, 'Analytics metrics retrieved.');
    }
}
