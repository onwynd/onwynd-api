<?php

namespace App\Http\Controllers\API\V1\Tech;

use App\Http\Controllers\API\BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SystemHealthController extends BaseController
{
    public function index()
    {
        // ── Database ──────────────────────────────────────────────────────────
        $dbStatus = 'Operational';
        $dbLatency = null;
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $dbLatency = round((microtime(true) - $start) * 1000, 1).'ms';
        } catch (\Exception) {
            $dbStatus = 'Degraded';
            $dbLatency = 'N/A';
        }

        // ── Redis ─────────────────────────────────────────────────────────────
        $redisStatus = 'Operational';
        $redisLatency = null;
        try {
            $start = microtime(true);
            Cache::store('redis')->get('__health_check__');
            $redisLatency = round((microtime(true) - $start) * 1000, 1).'ms';
        } catch (\Exception) {
            $redisStatus = 'Unavailable';
            $redisLatency = 'N/A';
        }

        // ── Memory ────────────────────────────────────────────────────────────
        $memUsed   = memory_get_usage(true);
        $memLimit  = $this->parseIniBytes(ini_get('memory_limit'));
        $memPct    = $memLimit > 0 ? round(($memUsed / $memLimit) * 100, 1) : null;

        // ── CPU load (Unix only) ──────────────────────────────────────────────
        $cpuLoad = null;
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $cpuLoad = round($load[0] * 100, 1); // 1-min average as %
        }

        // ── Disk ──────────────────────────────────────────────────────────────
        $diskTotal = @disk_total_space('/');
        $diskFree  = @disk_free_space('/');
        $diskUsedPct = ($diskTotal && $diskFree !== false)
            ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1)
            : null;

        // ── PHP / Process info ────────────────────────────────────────────────
        $phpVersion  = PHP_VERSION;
        $laravelVer  = app()->version();
        $environment = app()->environment();
        $uptime      = $this->getSystemUptime();

        $health = [
            'services' => [
                'database' => [
                    'status'  => $dbStatus,
                    'latency' => $dbLatency,
                ],
                'redis' => [
                    'status'  => $redisStatus,
                    'latency' => $redisLatency,
                ],
                'storage' => [
                    'status'  => 'Operational',
                    'latency' => null,
                ],
            ],
            'system' => [
                'cpu_load_pct'    => $cpuLoad,
                'memory_used_mb'  => round($memUsed / 1024 / 1024, 1),
                'memory_limit_mb' => $memLimit > 0 ? round($memLimit / 1024 / 1024, 1) : null,
                'memory_pct'      => $memPct,
                'disk_used_pct'   => $diskUsedPct,
                'disk_total_gb'   => $diskTotal ? round($diskTotal / 1024 / 1024 / 1024, 1) : null,
                'disk_free_gb'    => ($diskFree !== false) ? round($diskFree / 1024 / 1024 / 1024, 1) : null,
            ],
            'meta' => [
                'php_version'   => $phpVersion,
                'laravel'       => $laravelVer,
                'environment'   => $environment,
                'server_uptime' => $uptime,
            ],
            'last_check' => now()->toIso8601String(),
        ];

        return $this->sendResponse($health, 'System health status retrieved successfully.');
    }

    /**
     * Parse ini memory strings like "256M", "1G" into bytes.
     */
    private function parseIniBytes(string $val): int
    {
        $val  = trim($val);
        $last = strtolower(substr($val, -1));
        $num  = (int) $val;
        return match ($last) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }

    /**
     * Return system uptime (Linux) or a fallback.
     */
    private function getSystemUptime(): ?string
    {
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/uptime')) {
            $seconds = (int) explode(' ', file_get_contents('/proc/uptime'))[0];
            $days    = intdiv($seconds, 86400);
            $hours   = intdiv($seconds % 86400, 3600);
            $mins    = intdiv($seconds % 3600, 60);

            return "{$days}d {$hours}h {$mins}m";
        }

        return null;
    }
}
