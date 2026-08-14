<?php

namespace App\Services;

use App\Models\Central\Mutual;
use App\Models\Tenant\Member;
use App\Models\Tenant\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Operator-facing tenant health (Phase 3). For each mutual it opens the tenant
 * database and gathers light signals — reachability, member and active-subscription
 * counts, and last recorded activity — so the operator can spot a stalled or
 * unreachable tenant at a glance. Reachability failures are caught per tenant so
 * one broken database never breaks the whole sweep.
 */
class TenantHealthService
{
    /** @return array<int,array{id:string,name:string,status:string,reachable:bool,members:int,active_subscriptions:int,last_activity:?string}> */
    public function snapshot(): array
    {
        $rows = [];

        Mutual::orderBy('name')->get()->each(function (Mutual $mutual) use (&$rows) {
            $rows[] = $this->forMutual($mutual);
        });

        return $rows;
    }

    /** @return array{id:string,name:string,status:string,reachable:bool,members:int,active_subscriptions:int,last_activity:?string} */
    public function forMutual(Mutual $mutual): array
    {
        $base = [
            'id' => $mutual->id, 'name' => $mutual->name, 'status' => $mutual->status,
            'reachable' => false, 'members' => 0, 'active_subscriptions' => 0, 'last_activity' => null,
        ];

        try {
            $mutual->run(function () use (&$base) {
                $base['reachable'] = true;
                $base['members'] = Member::count();
                $base['active_subscriptions'] = Subscription::where('status', 'validated')->count();

                $last = DB::table('members')->max('updated_at');
                $base['last_activity'] = $last ? (string) $last : null;
            });
        } catch (\Throwable $e) {
            $base['reachable'] = false;
        }

        return $base;
    }
}
