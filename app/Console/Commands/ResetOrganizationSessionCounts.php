<?php

namespace App\Console\Commands;

use App\Models\Institutional\OrganizationMember;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetOrganizationSessionCounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'organizations:reset-session-counts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset monthly session counts for organization members';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting organization session count reset...');

        try {
            $now = Carbon::now();
            $startOfMonth = $now->startOfMonth();

            // Find organization members whose last reset was before the start of current month
            $membersToReset = OrganizationMember::where(function ($query) use ($startOfMonth) {
                $query->whereNull('last_reset_at')
                    ->orWhere('last_reset_at', '<', $startOfMonth);
            })->where('role', 'member');

            $count = $membersToReset->count();

            if ($count === 0) {
                $this->info('No organization members need session count reset.');

                return Command::SUCCESS;
            }

            $this->info("Found {$count} organization members to reset.");

            // Reset session counts and update last_reset_at
            $updated = $membersToReset->update([
                'sessions_used_this_month' => 0,
                'last_reset_at' => $now,
            ]);

            $this->info("Successfully reset session counts for {$updated} organization members.");

            // Log the reset for audit purposes
            Log::info('Organization session counts reset', [
                'count' => $updated,
                'timestamp' => $now->toIso8601String(),
                'reset_month' => $startOfMonth->format('Y-m'),
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to reset organization session counts: '.$e->getMessage());
            Log::error('Organization session count reset failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
