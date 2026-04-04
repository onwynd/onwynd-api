<?php

namespace App\Console\Commands;

use App\Models\Okr\OkrKeyResult;
use App\Models\Okr\OkrObjective;
use App\Models\User;
use App\Notifications\OkrWeeklyDigestNotification;
use App\Services\OkrProgressService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OkrWeeklyDigestCommand extends Command
{
    protected $signature = 'okr:weekly-digest
                            {--quarter= : Target quarter, e.g. Q2-2026 (defaults to current)}';

    protected $description = 'Send the weekly OKR digest email + in-app notification to executives and department leads.';

    /**
     * Role slugs that receive the weekly digest.
     * Maps to the 27 roles in the system — executives + all department leads.
     */
    const DIGEST_ROLES = [
        'ceo', 'coo', 'cgo', 'admin',
        'product_manager', 'manager', 'finance', 'marketing', 'sales', 'closer',
        'hr', 'support', 'compliance', 'legal_advisor', 'clinical_advisor', 'tech_team',
    ];

    public function __construct(
        private readonly OkrProgressService $progressService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $quarter    = $this->option('quarter') ?? $this->currentQuarter();
        $digestData = $this->buildDigest($quarter);

        $recipients = User::whereHas('role', fn ($q) => $q->whereIn('slug', self::DIGEST_ROLES))
            ->where('is_active', true)
            ->get();

        $sent = $failed = 0;

        foreach ($recipients as $user) {
            try {
                $user->notify(new OkrWeeklyDigestNotification($digestData, $quarter));
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('OKR: weekly digest send failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $this->info("OKR weekly digest — sent: {$sent}, failed: {$failed}, quarter: {$quarter}");
        Log::info('OKR: weekly digest dispatched', compact('quarter', 'sent', 'failed'));

        return self::SUCCESS;
    }

    private function buildDigest(string $quarter): array
    {
        $objectives = OkrObjective::with(['keyResults.owner:id,first_name,last_name', 'owner:id,first_name,last_name'])
            ->forQuarter($quarter)
            ->active()
            ->topLevel()
            ->get();

        $allKrs = OkrKeyResult::whereHas('objective', fn ($q) => $q->where('quarter', $quarter)->where('status', 'active'))->get();

        $attentionNeeded = OkrKeyResult::with(['objective:id,title', 'owner:id,first_name,last_name'])
            ->whereHas('objective', fn ($q) => $q->where('quarter', $quarter)->where('status', 'active'))
            ->atRiskOrOffTrack()
            ->orderByRaw("FIELD(health_status, 'off_track', 'at_risk')")
            ->get();

        return [
            'health_score'     => $this->progressService->companyHealthScore(),
            'objectives'       => $objectives,
            'totals'           => [
                'on_track'  => $allKrs->where('health_status', 'on_track')->count(),
                'at_risk'   => $allKrs->where('health_status', 'at_risk')->count(),
                'off_track' => $allKrs->where('health_status', 'off_track')->count(),
                'total'     => $allKrs->count(),
            ],
            'attention_needed' => $attentionNeeded,
            'week_of'          => now()->startOfWeek()->format('M j, Y'),
        ];
    }

    private function currentQuarter(): string
    {
        $month = now()->month;
        $year  = now()->year;
        $q     = match (true) {
            $month <= 3  => 'Q1',
            $month <= 6  => 'Q2',
            $month <= 9  => 'Q3',
            default      => 'Q4',
        };
        return "{$q}-{$year}";
    }
}
