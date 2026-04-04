<?php

namespace App\Http\Controllers\API\V1\Audit;

use App\Http\Controllers\Controller;
use App\Models\PageViewLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageViewController extends Controller
{
    /**
     * POST /api/v1/page-views
     * Called by sensitive dashboard pages on mount to record who viewed them.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'page_key'    => 'required|string|max:100',
            'record_type' => 'nullable|string|max:100',
            'record_id'   => 'nullable|integer',
        ]);

        PageViewLog::record(
            userId:     $user->id,
            pageKey:    $validated['page_key'],
            recordType: $validated['record_type'] ?? null,
            recordId:   $validated['record_id'] ?? null,
            ip:         $request->ip(),
            userAgent:  $request->userAgent(),
        );

        return response()->json(['ok' => true], 201);
    }

    /**
     * GET /api/v1/page-views?page_key=finance.statements&limit=50
     * Returns who viewed a particular page (audit/admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page_key'   => 'required|string|max:100',
            'record_id'  => 'nullable|integer',
            'limit'      => 'nullable|integer|min:1|max:200',
        ]);

        $logs = PageViewLog::with('user:id,first_name,last_name,email')
            ->where('page_key', $request->page_key)
            ->when($request->filled('record_id'), fn ($q) => $q->where('record_id', $request->record_id))
            ->orderByDesc('viewed_at')
            ->limit($request->integer('limit', 50))
            ->get();

        return response()->json([
            'page_key' => $request->page_key,
            'total'    => $logs->count(),
            'viewers'  => $logs->map(fn ($log) => [
                'user'      => $log->user,
                'viewed_at' => $log->viewed_at,
                'ip'        => $log->ip_address,
            ]),
        ]);
    }
}
