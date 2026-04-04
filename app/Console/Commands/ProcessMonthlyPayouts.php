<?php

namespace App\Console\Commands;

use App\Services\Finance\PayoutService;
use Illuminate\Console\Command;

class ProcessMonthlyPayouts extends Command
{
    protected $signature = 'onwynd:payouts:process {month? : YYYY-MM, default: current month}';

    protected $description = 'Generate payout batch and process transfers for therapists';

    public function handle(): int
    {
        $month = $this->argument('month') ?? now()->format('Y-m');
        $service = new PayoutService;

        $this->info('Generating payout batch for '.$month);
        $batch = $service->generatePayoutBatch($month);
        $this->info('Batch size: '.$batch['count']);

        foreach ($batch['payouts'] as $p) {
            $res = $service->initiateBankTransfer($p['id']);
            $this->line('Processed payout '.$p['id'].' ref='.$res['reference']);
        }

        $this->info('Monthly payouts processed');

        return self::SUCCESS;
    }
}
