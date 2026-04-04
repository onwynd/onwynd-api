<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\InactivityNudge;
use Illuminate\Console\Command;

class SendInactivityNudge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:send-inactivity-nudge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send inactivity nudges to users who haven\'t logged in for 7 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cutoff = now()->subDays(7);
        $start = (clone $cutoff)->subDay();
        $end = (clone $cutoff)->addDay();

        $users = User::whereHas('role', function ($q) {
            $q->where('slug', 'patient');
        })
            ->where('is_active', true)
            ->whereBetween('last_activity_at', [$start, $end])
            ->whereNull('inactivity_nudge_sent_at')
            ->get();

        $this->info("Found {$users->count()} users requiring inactivity nudge.");

        foreach ($users as $user) {
            $user->notify(new InactivityNudge);
            $user->update(['inactivity_nudge_sent_at' => now()]);
            $this->info("Inactivity nudge sent to: {$user->email}");
        }
    }
}
