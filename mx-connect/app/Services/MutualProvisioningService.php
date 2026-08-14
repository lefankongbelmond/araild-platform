<?php

namespace App\Services;

use App\Models\Central\Mutual;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a new mutual (tenant) from the Super Admin UI:
 *   1. inserts the mutual registry row (central),
 *   2. stancl/tenancy creates its dedicated database,
 *   3. runs the tenant migrations against that database,
 *   4. seeds initial parameters (roles, SYSCOHADA chart, risk matrix).
 *
 * Nothing is hard-coded per country — country/currency/locale/providers are passed in as ids.
 */
class MutualProvisioningService
{
    /**
     * @param  array{name:string,slug:string,country_id:int,currency_id:int,locale_id:int,region?:string,city?:string,approved_by?:int}  $data
     */
    public function create(array $data): Mutual
    {
        // Enforce configurable platform cap (0 = unlimited).
        $max = config('mxconnect.max_mutuals');
        if ($max > 0 && Mutual::where('status', '!=', 'suspended')->count() >= $max) {
            throw new \RuntimeException("Platform mutual limit ({$max}) reached.");
        }

        return DB::connection('central')->transaction(function () use ($data) {
            $id = 'mut_' . Str::padLeft((string) (Mutual::count() + 1), 2, '0');

            /** @var Mutual $mutual */
            $mutual = Mutual::create(array_merge($data, [
                'id'     => $id,
                'status' => 'pending',
            ]));

            // Attach the subdomain (mut_01 -> mut01.<central-domain>).
            $mutual->domains()->create([
                'domain' => str_replace('_', '', $id) . '.' . env('CENTRAL_DOMAIN', 'mx-connect.com'),
            ]);

            // stancl event pipeline (CreateDatabase, MigrateDatabase, SeedDatabase)
            // runs on tenant creation per config/tenancy.php. Seeding installs:
            // roles/permissions, SYSCOHADA chart of accounts, the 15-risk starter matrix.

            return $mutual;
        });
    }

    /** Approve (agrément) — flips status and stamps who/when, audited. */
    public function approve(Mutual $mutual, int $networkUserId): void
    {
        $mutual->update([
            'status'      => 'approved',
            'approved_at' => now()->toDateString(),
            'approved_by' => $networkUserId,
        ]);
    }
}
