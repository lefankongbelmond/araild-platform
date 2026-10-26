<?php

namespace App\Console\Commands;

use App\Services\TenantHealthService;
use Illuminate\Console\Command;

/** Operator health sweep across all tenants (Phase 3). */
class TenantsHealth extends Command
{
    protected $signature = 'mxconnect:tenants-health';
    protected $description = 'Report reachability and key counts for every mutual (per tenant DB).';

    public function handle(TenantHealthService $service): int
    {
        $rows = collect($service->snapshot())->map(fn ($r) => [
            $r['id'], $r['name'], $r['status'],
            $r['reachable'] ? 'OK' : 'UNREACHABLE',
            $r['members'], $r['active_subscriptions'],
            $r['last_activity'] ?? '—',
        ])->all();

        $this->table(['ID', 'Mutual', 'Status', 'DB', 'Members', 'Active subs', 'Last activity'], $rows);

        $unreachable = collect($service->snapshot())->where('reachable', false)->count();
        if ($unreachable > 0) {
            $this->warn("{$unreachable} tenant(s) unreachable.");
            return self::FAILURE;
        }

        $this->info('All tenants reachable.');
        return self::SUCCESS;
    }
}
