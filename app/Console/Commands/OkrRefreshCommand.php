<?php

namespace App\Console\Commands;

use App\Models\Okr\OkrKeyResult;
use App\Services\OkrAlertService;
use App\Services\OkrProgressService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OkrRefreshCommand extends Command
{
    protected $signature = 'okr:refresh
                            {--quarter= : Limit to a specific quarter, e.g. Q2-2026}';

    protected $description = 'Refresh auto-bound OKR key results from DashboardMetric and fire health alerts on transitions.';

    public function __construct(
        private readonly OkrProgressService $progressService,
        private readonly OkrAlertService $alertService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $quarter = $this->option('quarter');

        $query = OkrKeyResult::with(['objective', 'owner'])
            ->where('metric_type', 'auto')
            ->whereHas('objective', fn ($q) => $q->where('status', 'active'));

        if ($quarter) {
            $query->whereHas('objective', fn ($q) => $q->where('quarter', $quarter));
        }

        $krs   = $query->get();
        $total = $krs->count();

        Log::info('OKR: refresh started', ['total' => $total, 'quarter' => $quarter ?? 'all']);
        $this->info("Refreshing {$total} auto-bound key results…");

        $refreshed = $skipped = $alerted = $failed = 0;

        foreach ($krs as $kr) {
            try {
                $result = $this->progressService->refresh($kr);

                if ($result === null) {
                    $skipped++;
                    continue;
                }

                [$oldHealth, $newHealth] = $result;

                if ($oldHealth !== $newHealth) {
                    $this->alertService->dispatch($kr, $oldHealth, $newHealth);
                    $alerted++;
                    $this->line("  ⚡ KR #{$kr->id} health changed: {$oldHealth} → {$newHealth}");
                }

                $refreshed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('OKR: refresh failed for KR', ['kr_id' => $kr->id, 'error' => $e->getMessage()]);
                $this->warn("  ✗ KR #{$kr->id} failed: {$e->getMessage()}");
            }
        }

        $this->info("✓ Done — refreshed: {$refreshed}, skipped: {$skipped}, alerts: {$alerted}, failed: {$failed}");

        Log::info('OKR: refresh completed', compact('refreshed', 'skipped', 'alerted', 'failed'));

        return self::SUCCESS;
    }
}
