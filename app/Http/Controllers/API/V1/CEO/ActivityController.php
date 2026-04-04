<?php

namespace App\Http\Controllers\API\V1\CEO;

use App\Http\Controllers\API\BaseController;
use App\Models\Admin\AdminLog;
use Illuminate\Http\Request;

class ActivityController extends BaseController
{
    /**
     * GET /api/v1/ceo/activity
     * Query important platform activities across roles.
     * range: recent (24h) | 30days | all
     */
    public function index(Request $request)
    {
        $range = $request->input('range', 'recent');
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 25);

        // Important actions (extendable)
        $importantActions = [
            'upgrade_subscription',
            'create_user',
            'update_user',
            'suspend_user',
            'activate_user',
            'approve_therapist',
            'reject_therapist',
            'create_content',
            'delete_content',
            'update_settings',
            'toggle_plan_active',
        ];

        $query = AdminLog::with('user:id,first_name,last_name,email')
            ->whereIn('action', $importantActions);

        // Time filters
        if ($range === 'recent') {
            $query->where('created_at', '>=', now()->subDay());
        } elseif ($range === '30days') {
            $query->where('created_at', '>=', now()->subDays(30));
        } // 'all' has no extra filter

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('target_type', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $logs = $query->orderByDesc('created_at')->paginate($perPage);

        $data = $logs->getCollection()->map(function ($log) {
            $userName = $log->user ? trim(($log->user->first_name ?? '').' '.($log->user->last_name ?? '')) : null;

            return [
                'id' => $log->id,
                'timestamp' => $log->created_at,
                'action' => $log->action,
                'user_name' => $userName ?: ($log->user->email ?? null),
                'user_email' => $log->user->email ?? null,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'details' => $log->details,
                'ip_address' => $log->ip_address,
            ];
        });

        return $this->sendResponse([
            'data' => $data,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ], 'CEO activity retrieved.');
    }
}
