<?php

namespace App\Console\Commands;

use App\Mail\Corporate\PilotExpiredEmail;
use App\Mail\Corporate\PilotMidpointEmail;
use App\Mail\Corporate\PilotPreRenewalEmail;
use App\Models\InstitutionalContract;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPilotLifecycleNotifications extends Command
{
    protected $signature = 'pilots:notify';

    protected $description = 'Send lifecycle notification emails for corporate pilot contracts (midpoint, pre-renewal, expiry).';

    public function handle(): int
    {
        $today = Carbon::today();

        $this->sendMidpointNotifications($today);
        $this->sendPreRenewalNotifications($today);
        $this->sendExpiryNotifications($today);

        $this->info('Pilot lifecycle notifications processed.');

        return self::SUCCESS;
    }

    /**
     * Step 1 — Midpoint: fires on the calendar day that equals or first exceeds
     * 50% of the total pilot duration. Using a >= check (not ===) ensures it
     * always fires for pilots of any length, even non-round day counts.
     */
    private function sendMidpointNotifications(Carbon $today): void
    {
        $contracts = InstitutionalContract::where('status', 'active')
            ->whereNull('midpoint_notified_at')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->get();

        foreach ($contracts as $contract) {
            $start     = Carbon::parse($contract->start_date)->startOfDay();
            $end       = Carbon::parse($contract->end_date)->startOfDay();
            $totalDays = $start->diffInDays($end);

            if ($totalDays <= 0) {
                continue;
            }

            $elapsed  = $start->diffInDays($today);
            $halfwayDay = (int) ceil($totalDays / 2);

            // Fire on the first run on or after the midpoint day
            if ($elapsed < $halfwayDay) {
                continue;
            }

            $hrEmail = $this->resolveHrEmail($contract);
            if (! $hrEmail) {
                Log::warning('pilots:notify — no HR email for midpoint', ['contract_id' => $contract->id]);
                continue;
            }

            $sessionsUsed      = (int) ($contract->sessions_used ?? 0);
            $sessionsTotal     = (int) ($contract->total_sessions_quota ?? 0);
            $sessionsRemaining = max(0, $sessionsTotal - $sessionsUsed);
            $usageRatePct      = $sessionsTotal > 0
                ? round(($sessionsUsed / $sessionsTotal) * 100, 1)
                : 0.0;

            Mail::to($hrEmail)->queue(new PilotMidpointEmail(
                orgName:           $this->resolveOrgName($contract),
                hrName:            $this->resolveHrName($contract),
                pilotEnd:          $end,
                sessionsUsed:      $sessionsUsed,
                sessionsRemaining: $sessionsRemaining,
                sessionsTotal:     $sessionsTotal,
                usageRatePct:      $usageRatePct,
            ));

            $contract->update(['midpoint_notified_at' => now()]);

            Log::info('pilots:notify — midpoint email queued', ['contract_id' => $contract->id]);
        }
    }

    /**
     * Step 2 — Pre-renewal: pilot ends in exactly 14 days, pre_renewal_notified_at is null.
     */
    private function sendPreRenewalNotifications(Carbon $today): void
    {
        $targetDate = $today->copy()->addDays(14)->toDateString();

        $contracts = InstitutionalContract::where('status', 'active')
            ->whereNull('pre_renewal_notified_at')
            ->whereDate('end_date', $targetDate)
            ->get();

        foreach ($contracts as $contract) {
            $hrEmail = $this->resolveHrEmail($contract);
            if (! $hrEmail) {
                Log::warning('pilots:notify — no HR email for pre-renewal', ['contract_id' => $contract->id]);
                continue;
            }

            $renewalUrl = rtrim(config('app.frontend_url'), '/') . '/pricing/corporate';

            Mail::to($hrEmail)->queue(new PilotPreRenewalEmail(
                orgName:       $this->resolveOrgName($contract),
                hrName:        $this->resolveHrName($contract),
                pilotEnd:      Carbon::parse($contract->end_date),
                sessionsUsed:  (int) ($contract->sessions_used ?? 0),
                sessionsTotal: (int) ($contract->total_sessions_quota ?? 0),
                renewalUrl:    $renewalUrl,
            ));

            $contract->update(['pre_renewal_notified_at' => now()]);

            Log::info('pilots:notify — pre-renewal email queued', ['contract_id' => $contract->id]);
        }
    }

    /**
     * Step 3 — Expiry: pilot ended yesterday, expiry_notified_at is null.
     */
    private function sendExpiryNotifications(Carbon $today): void
    {
        $yesterday = $today->copy()->subDay()->toDateString();

        $contracts = InstitutionalContract::whereIn('status', ['active', 'expired'])
            ->whereNull('expiry_notified_at')
            ->whereDate('end_date', $yesterday)
            ->get();

        foreach ($contracts as $contract) {
            $hrEmail = $this->resolveHrEmail($contract);
            if (! $hrEmail) {
                Log::warning('pilots:notify — no HR email for expiry', ['contract_id' => $contract->id]);
                continue;
            }

            Mail::to($hrEmail)->queue(new PilotExpiredEmail(
                orgName:       $this->resolveOrgName($contract),
                hrName:        $this->resolveHrName($contract),
                expiryDate:    Carbon::parse($contract->end_date),
                sessionsUsed:  (int) ($contract->sessions_used ?? 0),
                sessionsTotal: (int) ($contract->total_sessions_quota ?? 0),
            ));

            $contract->update([
                'expiry_notified_at' => now(),
                'status'             => 'expired',
            ]);

            Log::info('pilots:notify — expiry email queued', ['contract_id' => $contract->id]);
        }
    }

    /**
     * Resolve the HR contact email for a contract.
     * Attempts: contract's institution_user email → company_name fallback.
     */
    private function resolveHrEmail(InstitutionalContract $contract): ?string
    {
        if ($contract->institution_user_id) {
            $user = \App\Models\User::find($contract->institution_user_id);
            if ($user && $user->email) {
                return $user->email;
            }
        }

        return null;
    }

    private function resolveHrName(InstitutionalContract $contract): string
    {
        if ($contract->institution_user_id) {
            $user = \App\Models\User::find($contract->institution_user_id);
            if ($user) {
                return trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'HR Director';
            }
        }

        return 'HR Director';
    }

    private function resolveOrgName(InstitutionalContract $contract): string
    {
        return $contract->company_name ?: 'Your Organisation';
    }
}
