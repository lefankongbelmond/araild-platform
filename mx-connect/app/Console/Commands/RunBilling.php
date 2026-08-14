<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;

/** Issue due invoices and flag overdue ones (scheduled daily). */
class RunBilling extends Command
{
    protected $signature = 'mxconnect:billing-run {--as-of=}';
    protected $description = 'Run the SaaS billing cycle: issue period invoices and flag overdue.';

    public function handle(BillingService $service): int
    {
        $result = $service->runCycle($this->option('as-of'));
        $this->info("Invoiced: {$result['invoiced']} · newly overdue: {$result['overdue']}.");
        return self::SUCCESS;
    }
}
