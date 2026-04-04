<?php

namespace App\Console\Commands;

use App\Mail\InstitutionalBiweeklyReport;
use App\Models\Partner;
use App\Models\PartnerUser;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendInstitutionalBiweeklyReport extends Command
{
    protected $signature = 'reports:send-institutional-biweekly
                            {--partner= : Send only to a specific partner UUID (for testing)}
                            {--dry-run  : Print report data without sending emails}';

    protected $description = 'Send biweekly performance reports to institutional partners';

    public function handle(): int
    {
        $periodEnd   = now();
        $periodStart = now()->subDays(14);

        $this->info("Biweekly institutional reports — {$periodStart->toDateString()} → {$periodEnd->toDateString()}");

        $query = Partner::where('status', 'active');

        if ($this->option('partner')) {
            $query->where('uuid', $this->option('partner'));
        }

        $partners = $query->get();

        if ($partners->isEmpty()) {
            $this->warn('No active partners found.');
            return self::SUCCESS;
        }

        foreach ($partners as $partner) {
            try {
                $stats = $this->gatherStats($partner, $periodStart, $periodEnd);

                if ($this->option('dry-run')) {
                    $this->line("  [{$partner->name}] " . json_encode($stats, JSON_PRETTY_PRINT));
                    continue;
                }

                // Send to the institution's contact email
                if (empty($partner->email)) {
                    $this->warn("  Skipping [{$partner->name}] — no email address on record.");
                    continue;
                }

                Mail::to($partner->email)->queue(
                    new InstitutionalBiweeklyReport($partner, $stats, $periodStart, $periodEnd)
                );

                Log::info('Biweekly institutional report queued', [
                    'partner_id'   => $partner->id,
                    'partner_name' => $partner->name,
                    'email'        => $partner->email,
                ]);

                $this->info("  ✓ Queued report for [{$partner->name}] → {$partner->email}");

            } catch (\Throwable $e) {
                Log::error('Failed to send biweekly institutional report', [
                    'partner_id' => $partner->id,
                    'error'      => $e->getMessage(),
                ]);
                $this->error("  ✗ Failed for [{$partner->name}]: {$e->getMessage()}");
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function gatherStats(Partner $partner, $periodStart, $periodEnd): array
    {
        // All user IDs linked to this partner
        $userIds = PartnerUser::where('partner_id', $partner->id)
            ->pluck('user_id');

        $totalUsers  = $userIds->count();
        $activeUsers = User::whereIn('id', $userIds)
            ->where('last_seen_at', '>=', $periodStart)
            ->count();

        // Sessions completed in the period by users of this partner
        $sessionsCompleted = TherapySession::whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->where('status', 'completed')
            ->count();

        $sessionsCancelled = TherapySession::whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->whereIn('status', ['cancelled', 'no_show'])
            ->count();

        // New users that joined in this period
        $newUsers = PartnerUser::where('partner_id', $partner->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->count();

        // Engagement rate (active / total)
        $engagementRate = $totalUsers > 0
            ? round(($activeUsers / $totalUsers) * 100, 1)
            : 0;

        return [
            'total_users'        => $totalUsers,
            'active_users'       => $activeUsers,
            'new_users'          => $newUsers,
            'engagement_rate'    => $engagementRate,
            'sessions_completed' => $sessionsCompleted,
            'sessions_cancelled' => $sessionsCancelled,
        ];
    }
}
