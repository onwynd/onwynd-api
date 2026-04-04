<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Notifications\OrgLowCredits;
use Illuminate\Console\Command;

class CheckLowCredits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orgs:check-low-credits';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check organizations for low session credits and notify administrators';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Find organizations with 3 or fewer credits remaining
        $organizations = Organization::where('credits_remaining', '<=', 3)
            ->where('credits_remaining', '>', 0)
            ->whereNull('low_credits_notified_at')
            ->get();

        $this->info("Found {$organizations->count()} organizations with low credits.");

        foreach ($organizations as $org) {
            $org->admin->notify(new OrgLowCredits($org->name, $org->credits_remaining));
            $org->update(['low_credits_notified_at' => now()]);
            $this->info("Low credit notification sent for: {$org->name}");
        }
    }
}
