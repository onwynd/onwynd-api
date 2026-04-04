<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\MailLog;
use Illuminate\Http\Request;

class MailLogController extends BaseController
{
    public function index(Request $request)
    {
        $query = MailLog::orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('recipient')) {
            $query->where('recipient', 'like', '%' . $request->recipient . '%');
        }
        if ($request->filled('mailable')) {
            $query->where('mailable_class', 'like', '%' . $request->mailable . '%');
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($b) use ($q) {
                $b->where('recipient', 'like', "%{$q}%")
                  ->orWhere('subject', 'like', "%{$q}%")
                  ->orWhere('mailable_class', 'like', "%{$q}%");
            });
        }

        $logs = $query->paginate($request->integer('per_page', 50));

        $stats = [
            'total'  => MailLog::count(),
            'sent'   => MailLog::where('status', 'sent')->count(),
            'failed' => MailLog::where('status', 'failed')->count(),
            // Recent failure rate (last 24 h)
            'failed_24h' => MailLog::where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())->count(),
            'sent_24h' => MailLog::where('status', 'sent')
                ->where('created_at', '>=', now()->subDay())->count(),
        ];

        return $this->sendResponse([
            'logs'       => $logs->items(),
            'pagination' => [
                'total'        => $logs->total(),
                'per_page'     => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
            ],
            'stats' => $stats,
        ], 'Mail logs retrieved.');
    }

    public function destroy(MailLog $mailLog)
    {
        $mailLog->delete();
        return $this->sendResponse([], 'Log entry deleted.');
    }

    public function purge(Request $request)
    {
        $request->validate([
            'older_than_days' => 'required|integer|min:1|max:365',
        ]);

        $deleted = MailLog::where('created_at', '<', now()->subDays($request->older_than_days))->delete();

        return $this->sendResponse(['deleted' => $deleted], "{$deleted} log entries purged.");
    }
}
