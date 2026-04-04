<?php

namespace App\Console\Commands;

use App\Services\ApprovalEngine;
use Illuminate\Console\Command;

class ProcessApprovalEscalations extends Command
{
    protected $signature   = 'approvals:escalate';
    protected $description = 'Send reminders for overdue approval steps and escalate past the deadline.';

    public function __construct(private readonly ApprovalEngine $engine)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Processing approval escalations…');
        $this->engine->processEscalations();
        $this->info('Done.');
        return self::SUCCESS;
    }
}
