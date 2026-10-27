<?php

namespace App\Services;

use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Guarantee versioning (CDC §11).
 *
 * Editing a guarantee's terms NEVER mutates the running version — that would
 * retroactively change past claims. Instead we CLOSE the current version
 * (valid_to = today) and OPEN a new one (valid_from = tomorrow). A version that
 * has already been used by a claim is `locked` and may never be edited.
 */
class GuaranteeVersioningService
{
    /** Create a brand-new guarantee with its first version. */
    public function createWithFirstVersion(array $guarantee, array $version): Guarantee
    {
        return DB::transaction(function () use ($guarantee, $version) {
            $g = Guarantee::create($guarantee);
            $g->versions()->create($version + [
                'version_no' => 1,
                'valid_from' => $version['valid_from'] ?? now()->toDateString(),
                'valid_to'   => null,
            ]);
            return $g;
        });
    }

    /**
     * Roll a guarantee to a new version. Closes the current open version the day
     * before the new one takes effect, then opens the successor.
     */
    public function newVersion(Guarantee $guarantee, array $params, ?string $effectiveFrom = null): GuaranteeVersion
    {
        $effectiveFrom = $effectiveFrom ?: now()->addDay()->toDateString();

        return DB::transaction(function () use ($guarantee, $params, $effectiveFrom) {
            /** @var GuaranteeVersion|null $current */
            $current = $guarantee->versions()->whereNull('valid_to')->latest('version_no')->first();

            if ($current) {
                $closeOn = Carbon::parse($effectiveFrom)->subDay()->toDateString();
                $current->update(['valid_to' => $closeOn]);
            }

            // Ce qui n'est pas modifié est repris de la version précédente (2026-11-01) :
            // réseau de soins, exclusions et plafonds ne se perdent plus à chaque nouvelle version.
            $params += ['provider_network' => $current?->provider_network, 'exclusions' => $current?->exclusions];

            $version = $guarantee->versions()->create($params + [
                'version_no' => ($current->version_no ?? 0) + 1,
                'valid_from' => $effectiveFrom,
                'valid_to'   => null,
            ]);
            if ($current) {
                foreach (\App\Models\Tenant\Ceiling::where('guarantee_version_id', $current->id)->get() as $ceiling) {
                    $ceiling->replicate()->fill(['guarantee_version_id' => $version->id])->save();
                }
            }

            return $version;
        });
    }

    /**
     * Réseau de soins d'une garantie (prestataires acceptés ; null = tous). Une version déjà
     * utilisée par un recours est immuable : on ouvre alors une nouvelle version à la date
     * choisie ; sinon la version en cours est modifiée directement.
     *
     * @param int[]|null $providerIds
     */
    public function setNetwork(Guarantee $guarantee, ?array $providerIds, ?string $effectiveFrom = null): GuaranteeVersion
    {
        $current = $guarantee->versions()->whereNull('valid_to')->latest('version_no')->firstOrFail();
        $network = $providerIds === null ? null : array_values(array_unique(array_map('intval', $providerIds)));
        if (! $current->locked) {
            $current->update(['provider_network' => $network]);

            return $current;
        }

        return $this->newVersion($guarantee, collect($current->only([
            'base_contribution_minor', 'periodicity', 'coverage_rate', 'copay_rate', 'membership_fee_minor', 'observation_days', 'exclusions',
        ]))->all() + ['provider_network' => $network], $effectiveFrom);
    }

    /**
     * Exclusions (actes non couverts) et plafonds d'une garantie (2026-11-03). Même règle que le
     * réseau : version non utilisée modifiée sur place ; version déjà utilisée par un recours →
     * nouvelle version datée, conditions financières et réseau repris, passé inchangé.
     *
     * @param int[] $exclusions  identifiants d'actes
     * @param array<int,array{act_type_id:?int,ceiling_minor:int,period:string}> $ceilings
     */
    public function setTerms(Guarantee $guarantee, array $exclusions, array $ceilings, ?string $effectiveFrom = null): GuaranteeVersion
    {
        $current = $guarantee->versions()->whereNull('valid_to')->latest('version_no')->firstOrFail();
        $exclusions = array_values(array_unique(array_map('intval', $exclusions)));
        $exclusions = $exclusions ?: null;

        return DB::transaction(function () use ($guarantee, $current, $exclusions, $ceilings, $effectiveFrom) {
            if ($current->locked) {
                $version = $this->newVersion($guarantee, collect($current->only([
                    'base_contribution_minor', 'periodicity', 'coverage_rate', 'copay_rate', 'membership_fee_minor', 'observation_days', 'provider_network',
                ]))->all() + ['exclusions' => $exclusions], $effectiveFrom);
            } else {
                $current->update(['exclusions' => $exclusions]);
                $version = $current;
            }
            \App\Models\Tenant\Ceiling::where('guarantee_version_id', $version->id)->delete();
            foreach ($ceilings as $c) {
                \App\Models\Tenant\Ceiling::create(['guarantee_version_id' => $version->id, 'act_type_id' => $c['act_type_id'] ?: null,
                    'ceiling_minor' => (int) $c['ceiling_minor'], 'period' => $c['period']]);
            }

            return $version;
        });
    }

    /** Guard: a locked version (already used by a claim) is immutable. */
    public function assertEditable(GuaranteeVersion $version): void
    {
        if ($version->locked) {
            throw new \RuntimeException('This guarantee version is locked and cannot be edited.');
        }
    }
}
