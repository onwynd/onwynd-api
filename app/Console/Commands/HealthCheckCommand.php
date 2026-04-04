<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Artisan health-check command for Onwynd.
 *
 * Usage:  php artisan onwynd:health
 * Output: JSON summary + exit code (0 = healthy, 1 = degraded).
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'onwynd:health {--json : Output result as JSON}';

    protected $description = 'Run a suite of infrastructure health checks (DB, Redis, queue, storage, AI keys)';

    private array $results = [];

    private bool $allOk = true;

    public function handle(): int
    {
        $this->checkDatabase();
        $this->checkRedis();
        $this->checkQueueWorker();
        $this->checkStorage();
        $this->checkEnvKeys();

        $summary = [
            'status' => $this->allOk ? 'healthy' : 'degraded',
            'checks' => $this->results,
            'checked_at' => now()->toIso8601String(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT));
        } else {
            $this->renderTable($summary);
        }

        return $this->allOk ? Command::SUCCESS : Command::FAILURE;
    }

    // -------------------------------------------------------------------------

    private function checkDatabase(): void
    {
        try {
            DB::select('SELECT 1');
            $this->pass('database', 'Connected');
        } catch (Exception $e) {
            $this->failCheck('database', $e->getMessage());
        }
    }

    private function checkRedis(): void
    {
        try {
            $key = 'health_check_ping_'.time();
            Cache::store('redis')->put($key, 'pong', 5);
            $val = Cache::store('redis')->get($key);
            Cache::store('redis')->forget($key);

            if ($val === 'pong') {
                $this->pass('redis', 'Read/write OK');
            } else {
                $this->failCheck('redis', 'Cache read returned unexpected value');
            }
        } catch (Exception $e) {
            $this->failCheck('redis', $e->getMessage());
        }
    }

    private function checkQueueWorker(): void
    {
        try {
            // Check the failed_jobs count as a proxy for queue health
            $failedCount = DB::table('failed_jobs')->count();
            if ($failedCount > 100) {
                $this->warnCheck('queue', "High failed job count: {$failedCount}");
            } else {
                $this->pass('queue', "Failed jobs: {$failedCount}");
            }
        } catch (Exception $e) {
            $this->failCheck('queue', $e->getMessage());
        }
    }

    private function checkStorage(): void
    {
        try {
            $path = 'health_check_'.time().'.txt';
            Storage::disk('local')->put($path, 'ok');
            $exists = Storage::disk('local')->exists($path);
            Storage::disk('local')->delete($path);

            if ($exists) {
                $this->pass('storage', 'Local disk read/write OK');
            } else {
                $this->failCheck('storage', 'Could not verify written file');
            }
        } catch (Exception $e) {
            $this->failCheck('storage', $e->getMessage());
        }
    }

    private function checkEnvKeys(): void
    {
        $required = [
            'OPENAI_API_KEY' => config('services.openai.api_key'),
            'STRIPE_SECRET_KEY' => config('services.stripe.secret'),
            'PAYSTACK_SECRET_KEY' => config('services.paystack.secret_key'),
            'PUSHER_APP_KEY' => config('broadcasting.connections.pusher.key'),
        ];

        $missing = [];
        foreach ($required as $name => $value) {
            if (empty($value)) {
                $missing[] = $name;
            }
        }

        if (empty($missing)) {
            $this->pass('env_keys', 'All required keys present');
        } else {
            $this->failCheck('env_keys', 'Missing: '.implode(', ', $missing));
        }
    }

    // -------------------------------------------------------------------------

    private function pass(string $check, string $message): void
    {
        $this->results[$check] = ['status' => 'ok', 'message' => $message];
    }

    private function failCheck(string $check, string $message): void
    {
        $this->results[$check] = ['status' => 'fail', 'message' => $message];
        $this->allOk = false;
    }

    private function warnCheck(string $check, string $message): void
    {
        $this->results[$check] = ['status' => 'warn', 'message' => $message];
    }

    private function renderTable(array $summary): void
    {
        $statusLabel = $summary['status'] === 'healthy' ? '<info>HEALTHY</info>' : '<error>DEGRADED</error>';
        $this->line("Onwynd Health Check — {$statusLabel}");
        $this->line("Checked at: {$summary['checked_at']}");
        $this->newLine();

        $rows = [];
        foreach ($summary['checks'] as $name => $result) {
            $icon = match ($result['status']) {
                'ok' => '<info>✓</info>',
                'warn' => '<comment>⚠</comment>',
                default => '<error>✗</error>',
            };
            $rows[] = [$icon, strtoupper($name), $result['message']];
        }

        $this->table(['', 'Check', 'Details'], $rows);
    }
}
