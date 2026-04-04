<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SystemHealthService
{
    public function getHealthMetrics()
    {
        return [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'disk_space' => $this->checkDiskSpace(),
            'load_average' => $this->checkLoadAverage(),
            'cache' => $this->checkCache(),
        ];
    }

    protected function checkDatabase()
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'healthy', 'latency' => $this->measureQueryTime()];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    protected function measureQueryTime()
    {
        $start = microtime(true);
        DB::select('SELECT 1');

        return round((microtime(true) - $start) * 1000, 2).'ms';
    }

    protected function checkRedis()
    {
        try {
            Redis::ping();

            return ['status' => 'healthy'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    protected function checkDiskSpace()
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;
        $percentage = round(($used / $total) * 100, 2);

        return [
            'status' => $percentage > 90 ? 'warning' : 'healthy',
            'used_percentage' => $percentage.'%',
            'free_gb' => round($free / 1024 / 1024 / 1024, 2).'GB',
        ];
    }

    protected function checkLoadAverage()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();

            return [
                '1min' => $load[0],
                '5min' => $load[1],
                '15min' => $load[2],
            ];
        }

        return ['status' => 'unknown'];
    }

    protected function checkCache()
    {
        try {
            Cache::put('health_check', true, 10);
            $value = Cache::get('health_check');

            return ['status' => $value ? 'healthy' : 'unhealthy'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }
}
