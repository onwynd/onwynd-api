<?php

namespace App\Http\Controllers\API\V1\Tech;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;

class LogsController extends BaseController
{
    public function index(Request $request)
    {
        // Mock logs
        $logs = [
            [
                'id' => 'log_1',
                'level' => 'INFO',
                'message' => 'User logged in',
                'service' => 'Auth Service',
                'timestamp' => now()->subMinutes(5)->toIso8601String(),
            ],
            [
                'id' => 'log_2',
                'level' => 'ERROR',
                'message' => 'Payment gateway timeout',
                'service' => 'Payment Service',
                'timestamp' => now()->subMinutes(15)->toIso8601String(),
            ],
            [
                'id' => 'log_3',
                'level' => 'WARNING',
                'message' => 'High memory usage detected',
                'service' => 'Worker Node 3',
                'timestamp' => now()->subHour()->toIso8601String(),
            ],
            [
                'id' => 'log_4',
                'level' => 'INFO',
                'message' => 'Scheduled backup completed',
                'service' => 'Database',
                'timestamp' => now()->subHours(4)->toIso8601String(),
            ],
            [
                'id' => 'log_5',
                'level' => 'CRITICAL',
                'message' => 'Connection refused to Redis',
                'service' => 'Cache Service',
                'timestamp' => now()->subDay()->toIso8601String(),
            ],
        ];

        // Wrap in pagination structure to match frontend expectations
        // This simulates a Laravel paginator response structure
        $paginated = [
            'data' => $logs,
            'current_page' => 1,
            'per_page' => 15,
            'total' => count($logs),
            'last_page' => 1,
            'from' => 1,
            'to' => count($logs),
        ];

        return $this->sendResponse($paginated, 'System logs retrieved successfully.');
    }
}
