<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

/** Scheduled backup of central + all tenant databases, logged to the DR register. */
class BackupDatabases extends Command
{
    protected $signature = 'mxconnect:backup';
    protected $description = 'Back up the central and all tenant databases; record the run in the DR log.';

    public function handle(BackupService $service): int
    {
        $drill = $service->run();

        $this->info("Backup recorded as {$drill->ref} — outcome: {$drill->outcome} ({$drill->rto_actual_minutes} min).");

        return $drill->outcome === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
