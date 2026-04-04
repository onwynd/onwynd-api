<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IPProtectionController extends BaseController
{
    /**
     * GET /api/v1/admin/ip-protection
     *
     * Get current IP protection settings for admin.
     */
    public function index()
    {
        $keys = [
            'ip_protection_web_enabled',
            'ip_protect_web_devtools',
            'ip_protect_web_rightclick',
            'ip_protect_web_textselection',
            'ip_protect_web_keyboard',
            'ip_protect_web_dragging',
            'ip_protect_web_log_attempts',
            'ip_protection_dashboard_enabled',
            'ip_protect_dash_devtools',
            'ip_protect_dash_rightclick',
            'ip_protect_dash_textselection',
            'ip_protect_dash_keyboard',
            'ip_protect_dash_clipboard',
            'ip_protect_dash_log_attempts',
        ];

        $settings = Setting::whereIn('key', $keys)->get()
            ->keyBy('key')
            ->map(fn ($s) => $s->value === 'true' || $s->value === '1');

        return $this->sendResponse($settings, 'IP protection settings retrieved.');
    }

    /**
     * POST /api/v1/admin/ip-protection
     *
     * Update IP protection settings.
     * Accessible by Admin, CEO, COO.
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $isCoo = $user->hasRole('coo') && ! $user->hasRole('admin') && ! $user->hasRole('ceo');

        $data = $request->validate([
            'ip_protection_web_enabled' => 'sometimes|boolean',
            'ip_protect_web_devtools' => 'sometimes|boolean',
            'ip_protect_web_rightclick' => 'sometimes|boolean',
            'ip_protect_web_textselection' => 'sometimes|boolean',
            'ip_protect_web_keyboard' => 'sometimes|boolean',
            'ip_protect_web_dragging' => 'sometimes|boolean',
            'ip_protect_web_log_attempts' => 'sometimes|boolean',
            'ip_protection_dashboard_enabled' => 'sometimes|boolean',
            'ip_protect_dash_devtools' => 'sometimes|boolean',
            'ip_protect_dash_rightclick' => 'sometimes|boolean',
            'ip_protect_dash_textselection' => 'sometimes|boolean',
            'ip_protect_dash_keyboard' => 'sometimes|boolean',
            'ip_protect_dash_clipboard' => 'sometimes|boolean',
            'ip_protect_dash_log_attempts' => 'sometimes|boolean',
        ]);

        // COO can only update dashboard settings
        if ($isCoo) {
            foreach ($data as $key => $value) {
                if (strpos($key, '_web_') !== false) {
                    return $this->sendError('Unauthorized. COOs can only manage dashboard protection.', 403);
                }
            }
        }

        DB::beginTransaction();
        try {
            foreach ($data as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key, 'group' => 'ip_protection'],
                    ['value' => $value ? 'true' : 'false', 'type' => 'boolean']
                );
            }

            // Invalidate public config cache
            Cache::forget('ip_protection_config');

            DB::commit();

            return $this->sendResponse($data, 'IP protection settings updated.');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->sendError('Failed to update settings: '.$e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/config/ip-protection/log
     *
     * Log a protection attempt.
     */
    public function logAttempt(Request $request)
    {
        $data = $request->validate([
            'platform' => 'required|string|in:web,dashboard',
            'attempt_type' => 'required|string|max:50',
            'page_path' => 'nullable|string|max:500',
        ]);

        $log = \App\Models\IPProtectionLog::create([
            'user_id' => $request->user()?->id,
            'platform' => $data['platform'],
            'attempt_type' => $data['attempt_type'],
            'page_path' => $data['page_path'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse($log, 'Attempt logged successfully.');
    }

    /**
     * GET /api/v1/admin/ip-protection/logs
     *
     * Returns IP protection attempt logs.
     * Paginated, 30 per page.
     */
    public function getLogs(Request $request)
    {
        $logs = \App\Models\IPProtectionLog::with('user:id,first_name,last_name,email')
            ->when($request->platform, fn ($q, $p) => $q->where('platform', $p))
            ->when($request->type, fn ($q, $t) => $q->where('attempt_type', $t))
            ->orderByDesc('created_at')
            ->paginate(30);

        return $this->sendResponse($logs, 'IP protection logs retrieved.');
    }
}
