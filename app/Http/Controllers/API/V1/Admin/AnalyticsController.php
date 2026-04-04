<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\IpBlock;
use App\Models\PageEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends BaseController
{
    /**
     * GET /api/v1/admin/analytics/overview
     * High-level stats for the dashboard analytics page.
     */
    public function overview(Request $request)
    {
        $days  = max(1, min(90, $request->integer('days', 7)));
        $since = now()->subDays($days);

        $base = PageEvent::where('created_at', '>=', $since);

        $total       = (clone $base)->count();
        $unique      = (clone $base)->distinct('session_id')->count('session_id');
        $newUsers    = (clone $base)->where('visitor_type', 'new')->distinct('session_id')->count('session_id');
        $returning   = max(0, $unique - $newUsers);
        $bots        = (clone $base)->whereIn('quality', ['bot', 'suspicious'])->count();
        $avgDuration = (clone $base)->where('quality', 'human')->avg('duration_ms') ?? 0;
        $avgScroll   = (clone $base)->where('quality', 'human')->avg('scroll_pct') ?? 0;

        // Top pages
        $topPages = (clone $base)
            ->where('quality', 'human')
            ->select('page', DB::raw('count(*) as views'))
            ->groupBy('page')
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        // Traffic by country
        $byCountry = (clone $base)
            ->whereNotNull('country')
            ->select('country', DB::raw('count(*) as sessions'))
            ->groupBy('country')
            ->orderByDesc('sessions')
            ->limit(15)
            ->get();

        // Top referrers
        $topReferrers = (clone $base)
            ->whereNotNull('referrer')
            ->where('referrer', '!=', '')
            ->where('quality', 'human')
            ->select('referrer', DB::raw('count(*) as count'))
            ->groupBy('referrer')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Daily page views (for chart)
        $daily = PageEvent::where('created_at', '>=', $since)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as views'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // UTM sources
        $utmSources = (clone $base)
            ->whereNotNull('utm_source')
            ->select('utm_source', DB::raw('count(*) as count'))
            ->groupBy('utm_source')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return $this->sendResponse([
            'period_days'   => $days,
            'total_views'   => $total,
            'unique_visitors' => $unique,
            'new_visitors'  => $newUsers,
            'returning_visitors' => $returning,
            'bots_blocked'  => $bots,
            'avg_duration_ms' => round($avgDuration),
            'avg_scroll_pct'  => round($avgScroll),
            'top_pages'     => $topPages,
            'by_country'    => $byCountry,
            'top_referrers' => $topReferrers,
            'utm_sources'   => $utmSources,
            'daily_views'   => $daily,
        ], 'Analytics overview retrieved.');
    }

    public function sessions(Request $request)
    {
        $rows = PageEvent::where('quality', 'human')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 50));
        return $this->sendResponse($rows, 'Sessions retrieved.');
    }

    // ── IP Blocks ────────────────────────────────────────────────────────────

    public function listBlocks(Request $request)
    {
        $blocks = IpBlock::with('blockedByUser:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 50));
        return $this->sendResponse($blocks, 'IP blocks retrieved.');
    }

    public function addBlock(Request $request)
    {
        $data = $request->validate([
            'ip_or_cidr' => 'required|string|max:50',
            'reason'     => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $block = IpBlock::create([
            ...$data,
            'is_active'  => true,
            'blocked_by' => $request->user()->id,
        ]);

        return $this->sendResponse($block, 'IP blocked.', 201);
    }

    public function removeBlock(IpBlock $block)
    {
        $block->update(['is_active' => false]);
        return $this->sendResponse([], 'Block deactivated.');
    }

    public function deleteBlock(IpBlock $block)
    {
        $block->delete();
        return $this->sendResponse([], 'Block deleted.');
    }
}
