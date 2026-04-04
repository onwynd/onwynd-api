<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PublicStatsController extends BaseController
{
    public function stats()
    {
        try {
            $stats = Cache::remember('onwynd_public_stats', 3600, function () {
                $totalUsers = DB::table('users')
                    ->whereHas !== null ? DB::table('users')->count() : 0;

                $totalUsersCount = DB::table('users')->count();

                $totalSessionsCompleted = DB::table('sessions')
                    ->where('status', 'completed')
                    ->count();

                $totalTherapistsActive = DB::table('therapist_profiles')
                    ->where('is_verified', true)
                    ->where('is_accepting_clients', true)
                    ->count();

                return [
                    'total_users' => $totalUsersCount,
                    'total_sessions_completed' => $totalSessionsCompleted,
                    'total_therapists_active' => $totalTherapistsActive,
                ];
            });

            return $this->sendResponse($stats, 'Public stats retrieved.');
        } catch (\Throwable $e) {
            return $this->sendResponse([
                'total_users' => 0,
                'total_sessions_completed' => 0,
                'total_therapists_active' => 0,
            ], 'Stats unavailable.');
        }
    }
}
