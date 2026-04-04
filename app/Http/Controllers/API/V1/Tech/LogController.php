<?php

namespace App\Http\Controllers\API\V1\Tech;

use App\Http\Controllers\API\BaseController;
use App\Models\Admin\AdminLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogController extends BaseController
{
    /**
     * Get system logs or admin activity logs.
     * Currently returns AdminLogs mapped to a generic log structure,
     * but could be extended to read Laravel daily logs.
     */
    public function index(Request $request): JsonResponse
    {
        // For the Tech Dashboard, we might want both System Logs and User Activity Logs.
        // Let's return AdminLogs for now as "System Activity"

        $query = AdminLog::with('user:id,first_name,last_name,email');

        if ($request->has('level')) {
            // AdminLog doesn't have level, but we can simulate or filter by action type if needed
            // For now, ignore or map actions to levels
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(50);

        // Transform to match frontend LogEntry interface
        // interface LogEntry { level: string, message: string, service: string, timestamp: string }

        $transformed = $logs->getCollection()->map(function ($log) {
            return [
                'id' => $log->id,
                'level' => 'INFO', // Default for user actions
                'message' => "{$log->user->first_name} performed {$log->action}",
                'service' => 'Admin Panel',
                'created_at' => $log->created_at->toIso8601String(),
                'details' => $log->details,
            ];
        });

        $logs->setCollection($transformed);

        return $this->sendResponse($logs, 'Logs retrieved successfully.');
    }

    /**
     * Get raw system logs (laravel.log) - simpler version
     */
    public function systemLogs(): JsonResponse
    {
        $logPath = storage_path('logs/laravel.log');

        if (! File::exists($logPath)) {
            return $this->sendResponse([], 'No system logs found.');
        }

        // Simple parsing of last 100 lines
        $lines = array_slice(file($logPath), -100);

        $parsedLogs = [];
        foreach (array_reverse($lines) as $index => $line) {
            // Regex to parse default Laravel log format
            if (preg_match('/^\[(?P<date>.*)\] (?P<env>\w+)\.(?P<level>\w+): (?P<message>.*)/', $line, $matches)) {
                $parsedLogs[] = [
                    'id' => 'sys-'.$index,
                    'timestamp' => $matches['date'],
                    'level' => strtoupper($matches['level']),
                    'service' => 'System',
                    'message' => $matches['message'],
                ];
            }
        }

        return $this->sendResponse($parsedLogs, 'System logs retrieved.');
    }
}
