<?php

namespace App\Services;

use App\Models\Central\Mutual;
use App\Models\Central\MutualOffer;
use App\Models\Tenant\GuaranteeVersion;

/**
 * Builds each mutual's public offer summary (CDC Phase 4, §30) from its CURRENT
 * guarantee versions and upserts it centrally for the comparator. Contributions
 * are normalised to a monthly equivalent so mutuals with different periodicities
 * are comparable. The summarisation is pure and unit-tested; publishing wraps it
 * with tenant-scoped reads and the central upsert.
 */
class OfferPublicationService
{
    /** Monthly equivalent of a base contribution given its periodicity. */
    public function monthlyEquivalent(int $baseMinor, string $periodicity): int
    {
        return match ($periodicity) {
            'annual'    => intdiv($baseMinor, 12),
            'quarterly' => intdiv($baseMinor, 3),
            default     => $baseMinor, // monthly
        };
    }

    /**
     * Summarise a set of current guarantee versions.
     * @param array<int,array{name:string,base_contribution_minor:int,periodicity:string,coverage_rate:float,membership_fee_minor:int}> $versions
     * @return array{guarantees_count:int,coverage_min:?float,coverage_max:?float,monthly_contribution_min_minor:?int,monthly_contribution_max_minor:?int,membership_fee_min_minor:?int,membership_fee_max_minor:?int,highlights:array<int,string>}
     */
    public function summarize(array $versions): array
    {
        if (empty($versions)) {
            return [
                'guarantees_count' => 0,
                'coverage_min' => null, 'coverage_max' => null,
                'monthly_contribution_min_minor' => null, 'monthly_contribution_max_minor' => null,
                'membership_fee_min_minor' => null, 'membership_fee_max_minor' => null,
                'highlights' => [],
            ];
        }

        $coverages = array_map(fn ($v) => (float) $v['coverage_rate'], $versions);
        $monthly = array_map(fn ($v) => $this->monthlyEquivalent((int) $v['base_contribution_minor'], $v['periodicity']), $versions);
        $fees = array_map(fn ($v) => (int) $v['membership_fee_minor'], $versions);

        return [
            'guarantees_count' => count($versions),
            'coverage_min' => min($coverages),
            'coverage_max' => max($coverages),
            'monthly_contribution_min_minor' => min($monthly),
            'monthly_contribution_max_minor' => max($monthly),
            'membership_fee_min_minor' => min($fees),
            'membership_fee_max_minor' => max($fees),
            'highlights' => array_slice(array_values(array_unique(array_map(fn ($v) => $v['name'], $versions))), 0, 6),
        ];
    }

    /** Publish (upsert) the offer for one mutual by reading its current versions. */
    public function publishForMutual(Mutual $mutual): MutualOffer
    {
        $versions = [];

        try {
            $mutual->run(function () use (&$versions) {
                $versions = GuaranteeVersion::query()
                    ->whereNull('valid_to')                                   // current version
                    ->whereHas('guarantee', fn ($q) => $q->where('status', 'active'))
                    ->with('guarantee:id,name')
                    ->get()
                    ->map(fn (GuaranteeVersion $v) => [
                        'name' => $v->guarantee->name ?? '—',
                        'base_contribution_minor' => (int) $v->base_contribution_minor,
                        'periodicity' => $v->periodicity,
                        'coverage_rate' => (float) $v->coverage_rate,
                        'membership_fee_minor' => (int) $v->membership_fee_minor,
                    ])->all();
            });
        } catch (\Throwable $e) {
            $versions = [];
        }

        $summary = $this->summarize($versions);

        return MutualOffer::updateOrCreate(
            ['mutual_id' => $mutual->id],
            $summary + ['currency_id' => $mutual->currency_id, 'synced_at' => now()],
        );
    }

    /** Publish offers for every approved mutual. */
    public function publishAll(): int
    {
        $count = 0;
        Mutual::where('status', 'approved')->get()->each(function (Mutual $mutual) use (&$count) {
            $this->publishForMutual($mutual);
            $count++;
        });
        return $count;
    }
}
