<?php

namespace App\Http\Controllers\API\V1\Support;

use App\Http\Controllers\API\BaseController;
use App\Models\SupportTicket;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    public function index(Request $request)
    {
        $user = $request->user();

        $stats = [
            'my_open_tickets' => SupportTicket::where('assigned_to', $user->id)
                ->where('status', 'open')
                ->count(),
            'total_open_tickets' => SupportTicket::where('status', 'open')->count(),
            'unassigned_tickets' => SupportTicket::whereNull('assigned_to')
                ->where('status', 'open')
                ->count(),
            'resolved_today' => SupportTicket::where('status', 'resolved')
                ->whereDate('resolved_at', today())
                ->count(),
        ];

        return $this->sendResponse($stats, 'Support dashboard data retrieved successfully.');
    }
}
