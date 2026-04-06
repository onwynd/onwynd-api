<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\V1\Admin\ReportController;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SendWeeklyAdminReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:send-weekly-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and send the weekly performance report to admins';

    /**
     * Execute the console command.
     */
    public function handle(ReportController $controller)
    {
        $this->info('Starting weekly admin report generation...');

        // Find all admins
        $admins = User::whereHas('roles', function ($q) {
            $q->where('role', 'admin');
        })->get();

        if ($admins->isEmpty()) {
            $this->warn('No admins found to send report to.');

            return;
        }

        foreach ($admins as $admin) {
            try {
                // Mock a request acting as this admin
                $request = new Request;
                $request->setUserResolver(function () use ($admin) {
                    return $admin;
                });

                $controller->sendWeeklyEmail($request);
                $this->info("Report sent to admin: {$admin->email}");
            } catch (\Exception $e) {
                $this->error("Failed to send report to {$admin->email}: ".$e->getMessage());
                Log::error("Weekly Report Command Error for {$admin->email}: ".$e->getMessage());
            }
        }

        $this->info('Weekly admin report process completed.');
    }
}
