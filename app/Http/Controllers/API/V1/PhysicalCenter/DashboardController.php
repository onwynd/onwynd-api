<?php

namespace App\Http\Controllers\API\V1\PhysicalCenter;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    public function index(Request $request)
    {
        // Mock data for Physical Center (Health Personnel) dashboard
        // In a real app, this would query appointments, check-ins, equipment status, etc.
        $data = [
            'overview' => [
                'todays_checkins' => 12,
                'active_sessions' => 3,
                'available_rooms' => 5,
                'equipment_maintenance_due' => 1,
            ],
            'upcoming_appointments' => [
                ['time' => '10:00 AM', 'patient' => 'Alice Smith', 'service' => 'VR Therapy'],
                ['time' => '11:00 AM', 'patient' => 'Bob Jones', 'service' => 'Counseling'],
            ],
            'equipment_status' => [
                'vr_headsets' => ['total' => 10, 'available' => 8, 'in_use' => 2],
                'massage_chairs' => ['total' => 4, 'available' => 3, 'maintenance' => 1],
            ],
        ];

        return $this->sendResponse($data, 'Physical Center dashboard data retrieved successfully.');
    }
}
