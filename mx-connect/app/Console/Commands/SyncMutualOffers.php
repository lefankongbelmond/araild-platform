<?php

namespace App\Console\Commands;

use App\Services\OfferPublicationService;
use Illuminate\Console\Command;

/** Refresh the public offer summaries used by the comparator. */
class SyncMutualOffers extends Command
{
    protected $signature = 'mxconnect:sync-offers';
    protected $description = 'Rebuild each mutual\'s public offer summary from its current guarantees.';

    public function handle(OfferPublicationService $service): int
    {
        $n = $service->publishAll();
        $this->info("Published offers for {$n} mutual(s).");
        return self::SUCCESS;
    }
}
