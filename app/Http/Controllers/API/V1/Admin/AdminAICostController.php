<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAICostController extends BaseController
{
    public function costSummary(Request $request)
    {
        try {
            $now = now();
            $last30 = $now->copy()->subDays(30);
            $prev30 = $now->copy()->subDays(60);

            // Total AI inference cost across all conversations
            $totalCost = DB::table('ai_conversations')
                ->whereNotNull('total_cost')
                ->sum('total_cost');

            // Average cost per conversation
            $avgCost = DB::table('ai_conversations')
                ->whereNotNull('total_cost')
                ->avg('total_cost') ?? 0;

            // Cost by subscription tier (join users table)
            $costByTier = DB::table('ai_conversations')
                ->join('users', 'ai_conversations.user_id', '=', 'users.id')
                ->whereNotNull('ai_conversations.total_cost')
                ->selectRaw('users.subscription_status as tier, SUM(ai_conversations.total_cost) as total, COUNT(*) as conversations')
                ->groupBy('users.subscription_status')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->tier ?? 'unknown' => [
                    'total' => round($row->total ?? 0, 4),
                    'conversations' => $row->conversations,
                ]])
                ->toArray();

            // Cost last 30 days vs previous 30 days
            $last30Cost = DB::table('ai_conversations')
                ->whereNotNull('total_cost')
                ->where('created_at', '>=', $last30)
                ->sum('total_cost');

            $prev30Cost = DB::table('ai_conversations')
                ->whereNotNull('total_cost')
                ->whereBetween('created_at', [$prev30, $last30])
                ->sum('total_cost');

            $trendPct = $prev30Cost > 0
                ? round((($last30Cost - $prev30Cost) / $prev30Cost) * 100, 2)
                : 0;

            // Daily trend for last 30 days
            $dailyTrend = DB::table('ai_conversations')
                ->whereNotNull('total_cost')
                ->where('created_at', '>=', $last30)
                ->selectRaw('DATE(created_at) as date, SUM(total_cost) as cost, COUNT(*) as conversations')
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'cost' => round($row->cost ?? 0, 4),
                    'conversations' => $row->conversations,
                ])
                ->toArray();

            return $this->sendResponse([
                'total_cost' => round($totalCost, 4),
                'avg_cost_per_conversation' => round($avgCost, 6),
                'cost_by_tier' => $costByTier,
                'last_30_days_cost' => round($last30Cost, 4),
                'prev_30_days_cost' => round($prev30Cost, 4),
                'trend_percentage' => $trendPct,
                'daily_trend' => $dailyTrend,
            ], 'AI cost summary retrieved.');
        } catch (\Throwable $e) {
            return $this->sendError('Failed to retrieve AI cost summary.', ['error' => $e->getMessage()], 500);
        }
    }
}
