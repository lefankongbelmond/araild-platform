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
            // Catalogue (2026-11-18) : paramètres de version repris, auteur de la nouvelle version.
            if ($current && \Illuminate\Support\Facades\Schema::hasColumn('guarantee_versions', 'period_months')) {
                $params += collect($current->only(['period_months', 'senior_age', 'renewal_waiver']))->all();
                $params += ['created_by' => auth('web')->id()];
            }

            $version = $guarantee->versions()->create($params + [
                'version_no' => ($current->version_no ?? 0) + 1,
                'valid_from' => $effectiveFrom,
                'valid_to'   => null,
            ]);
            if ($current) {
                foreach (\App\Models\Tenant\Ceiling::where('guarantee_version_id', $current->id)->get() as $ceiling) {
                    $ceiling->replicate()->fill(['guarantee_version_id' => $version->id])->save();
                }
                $this->copyCatalog($current, $version);
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

    /**
     * Garanties, plafonds et carences recopiés dans la nouvelle version (2026-11-18). Les garanties
     * gardent leur rule_key : les consommations de l'année restent comptées d'une version à l'autre.
     */
    public function copyCatalog(GuaranteeVersion $from, GuaranteeVersion $to): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('coverage_rules')) {
            return;
        }
        foreach (\App\Models\Tenant\CoverageRule::where('guarantee_version_id', $from->id)->get() as $r) {
            $r->replicate()->fill(['guarantee_version_id' => $to->id])->save();
        }
        foreach (\App\Models\Tenant\CoverageLimit::where('guarantee_version_id', $from->id)->get() as $l) {
            $l->replicate()->fill(['guarantee_version_id' => $to->id])->save();
        }
        foreach (\App\Models\Tenant\WaitingPeriod::where('guarantee_version_id', $from->id)->get() as $w) {
            $w->replicate()->fill(['guarantee_version_id' => $to->id])->save();
        }
    }

    /**
     * Version à modifier pour le catalogue : la version en cours si elle n'a encore servi à aucune
     * prestation ; sinon une NOUVELLE version datée (passé intact, aucune rétroactivité). Une version
     * future déjà ouverte (pas encore utilisée) est modifiée directement.
     */
    public function editableForCatalog(Guarantee $guarantee, ?string $effectiveFrom, ?string $note = null): GuaranteeVersion
    {
        $current = $guarantee->versions()->whereNull('valid_to')->latest('version_no')->firstOrFail();
        if (! $current->locked) {
            return $current;
        }
        $base = collect($current->only(['base_contribution_minor', 'periodicity', 'coverage_rate', 'copay_rate', 'membership_fee_minor', 'observation_days']))->all();

        return $this->newVersion($guarantee, $base + ['change_note' => $note], $effectiveFrom);
    }

    /** Guard: a locked version (already used by a claim) is immutable. */
    public function assertEditable(GuaranteeVersion $version): void
    {
        if ($version->locked) {
            throw new \RuntimeException('This guarantee version is locked and cannot be edited.');
        }
    }
}
