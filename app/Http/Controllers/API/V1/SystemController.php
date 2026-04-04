<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Models\MaintenanceSchedule;

class SystemController extends BaseController
{
    /**
     * Check system status and upcoming maintenance.
     */
    public function status()
    {
        // Check for active maintenance
        $activeMaintenance = MaintenanceSchedule::where('status', 'in_progress')
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->first();

        // Check for upcoming maintenance (next 24 hours)
        $upcomingMaintenance = MaintenanceSchedule::where('status', 'scheduled')
            ->where('start_time', '>', now())
            ->where('start_time', '<=', now()->addHours(24))
            ->orderBy('start_time', 'asc')
            ->first();

        return $this->sendResponse([
            'status' => $activeMaintenance ? 'maintenance' : 'operational',
            'active_maintenance' => $activeMaintenance,
            'upcoming_maintenance' => $upcomingMaintenance,
            'server_time' => now()->toIso8601String(),
        ], 'System status retrieved.');
    }
}
