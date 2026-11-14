<?php

namespace App\Services\Tenant;

use App\Models\Central\Currency;
use App\Models\Tenant\CoverageRule;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\ServiceCategory;
use App\Services\GuaranteeVersioningService;
use App\Support\TestEnvironment;

/**
 * Formules d'EXEMPLE de la mission, chargées seulement dans la MUTUELLE TEST et marquées « Exemple »
 * (guarantees.is_example). Seules les valeurs données par la mission sont reprises :
 *  - FAMILLE : consultation 80 %, pharmacie 70 %, analyses 80 %, hospitalisation 80 %, imagerie 70 %
 *    (ticket modérateur = le complément), plafond annuel pharmacie 30 000 par bénéficiaire, et le
 *    tableau des délais de carence (consultation et pharmacie ; hospitalisation) ;
 *  - ESSENTIEL et SCOLAIRE : plafond annuel pharmacie 25 000 et 15 000, enregistrés INACTIFS tant que la
 *    mutuelle n'a pas défini la garantie pharmacie de ces formules (aucun taux n'est inventé) ;
 *  - CONFORT, SENIOR, PERSONNALISÉE : formules en brouillon, garanties à définir.
 * Aucune cotisation n'est inventée (0, à fixer par une nouvelle version).
 */
class ExampleFormulaService
{
    /** Tableau des délais de carence de la mission : catégorie => [consultation et pharmacie, hospitalisation]. */
    public const WAITING = [
        'adult' => [30, 60], 'child_0_5' => [15, 30], 'child_6_17' => [30, 60],
        'student' => [15, 30], 'senior' => [30, 90], 'new_spouse' => [30, 60],
    ];

    public function __construct(private GuaranteeVersioningService $versioning) {}

    /** @return int nombre de formules créées (0 si déjà chargées) */
    public function load(): int
    {
        abort_unless(TestEnvironment::active(), 403);
        $cur = Currency::on('central')->find(tenant('currency_id')) ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
        $cat = fn (string $code) => ServiceCategory::where('code', $code)->value('id');
        $note = 'Exemple de la mission « Mutuelle paramétrable » (mutuelle de test). Valeurs modifiables ; elles ne sont ni des obligations réglementaires ni des normes actuarielles.';
        $defs = [
            'EX-ESSENTIEL' => ['ESSENTIEL (exemple)', 'draft', 25000],
            'EX-FAMILLE' => ['FAMILLE (exemple)', 'test', 30000],
            'EX-SCOLAIRE' => ['SCOLAIRE (exemple)', 'draft', 15000],
            'EX-CONFORT' => ['CONFORT (exemple)', 'draft', null],
            'EX-SENIOR' => ['SENIOR (exemple)', 'draft', null],
            'EX-PERSO' => ['FORMULE PERSONNALISÉE (exemple)', 'draft', null],
        ];
        $n = 0;
        foreach ($defs as $code => [$name, $status, $pharmacy]) {
            if (Guarantee::withTrashed()->where('code', $code)->exists()) {
                continue;
            }
            $g = $this->versioning->createWithFirstVersion(
                guarantee: ['code' => $code, 'name' => $name, 'status' => 'active', 'description' => $note,
                    'catalog_status' => $status, 'is_example' => true],
                version: ['base_contribution_minor' => 0, 'periodicity' => 'monthly', 'coverage_rate' => 0, 'copay_rate' => 0,
                    'membership_fee_minor' => 0, 'observation_days' => 0, 'valid_from' => now()->toDateString()],
            );
            $v = $g->currentVersion()->first();
            $n++;

            if ($code === 'EX-FAMILLE') {
                $rules = [];
                foreach ([['consultation', 'Consultation', 80], ['pharmacy', 'Pharmacie', 70], ['lab_tests', 'Analyses', 80],
                    ['hospitalization', 'Hospitalisation', 80], ['imaging', 'Imagerie', 70]] as $i => [$c, $label, $rate]) {
                    $rules[$c] = CoverageRule::create(['guarantee_version_id' => $v->id, 'service_category_id' => $cat($c), 'label' => $label,
                        'beneficiary_category' => 'all', 'coverage_rate' => $rate, 'copay_rate' => 100 - $rate, 'sort' => ($i + 1) * 10]);
                }
                foreach (self::WAITING as $bc => [$short, $hosp]) {
                    foreach (['consultation' => $short, 'pharmacy' => $short, 'hospitalization' => $hosp] as $c => $days) {
                        $v->waitingPeriods()->create(['rule_key' => $rules[$c]->rule_key, 'beneficiary_category' => $bc, 'days' => $days]);
                    }
                }
                $v->limits()->create(['label' => 'Plafond annuel pharmacie (exemple)', 'rule_keys' => [$rules['pharmacy']->rule_key],
                    'scope' => 'year', 'holder' => 'beneficiary', 'amount_minor' => $cur->toMinor($pharmacy), 'active' => true]);
            } elseif ($pharmacy) {
                // Pas de garantie pharmacie définie : plafond conservé INACTIF (à rattacher, puis activer).
                $v->limits()->create(['label' => 'Plafond annuel pharmacie (exemple, à rattacher à la garantie pharmacie)', 'rule_keys' => null,
                    'scope' => 'year', 'holder' => 'beneficiary', 'amount_minor' => $cur->toMinor($pharmacy), 'active' => false]);
            }
        }

        return $n;
    }
}
