<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Currency;
use App\Models\Tenant\ActType;
use App\Models\Tenant\CoverageLimit;
use App\Models\Tenant\CoverageRule;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\ServiceCategory;
use App\Models\Tenant\WaitingPeriod;
use App\Services\GuaranteeVersioningService;
use App\Services\Tenant\CoverageEngine;
use App\Support\CatalogLabels;
use App\Support\TestEnvironment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * CATALOGUE DES FORMULES (mission « Mutuelle paramétrable », L1, 2026-11-18).
 *
 * Une FORMULE (table guarantees) contient, pour chaque version datée, des GARANTIES détaillées
 * (catégorie de soins × catégorie de bénéficiaire : taux, ticket modérateur, conditions), des
 * PLAFONDS et des DÉLAIS DE CARENCE. Tout est saisi par l'administrateur : aucune valeur par défaut.
 *
 * Règle de versionnement (identique au réseau et aux plafonds classiques) : une version déjà
 * utilisée par une prestation est VERROUILLÉE ; toute modification ouvre une nouvelle version à
 * la date d'effet choisie, avec une copie des garanties (même identité rule_key) : les soins
 * déjà réalisés gardent leur calcul, les plafonds de l'année continuent de se consommer.
 */
class FormulaCatalogController extends Controller
{
    public function __construct(private GuaranteeVersioningService $versioning, private CoverageEngine $engine) {}

    public function index()
    {
        $guarantees = Guarantee::with(['currentVersion' => fn ($q) => $q->withCount(['rules', 'limits', 'waitingPeriods'])])->orderBy('name')->get();

        return view('tenant.formulas.index', [
            'guarantees' => $guarantees,
            'currency'   => $this->currency(),
            'unmapped'   => ActType::whereNull('service_category_id')->count(),
            'isTest'     => TestEnvironment::active(),
            'hasExamples' => Guarantee::where('is_example', true)->exists(),
        ]);
    }

    public function show(Guarantee $guarantee)
    {
        $current = $this->current($guarantee);
        $current->load(['rules.category', 'limits', 'waitingPeriods']);

        return view('tenant.formulas.show', [
            'g' => $guarantee, 'v' => $current, 'currency' => $this->currency(),
            'versions' => $guarantee->versions()->withCount('rules')->orderByDesc('version_no')->get(),
            'categories' => ServiceCategory::where('active', true)->orderBy('sort')->get(),
            'acts' => ActType::orderBy('label')->get(['id', 'label', 'service_category_id', 'tariff_minor']),
            'ruleNames' => $current->rules->pluck('label', 'rule_key'),
            'simulation' => session('simulation'),
        ]);
    }

    /** Fiche de la formule (description commerciale) : non versionnée, sans effet sur le calcul. */
    public function update(Request $request, Guarantee $guarantee)
    {
        $d = $request->validate([
            'name'              => ['required', 'string', 'max:120'],
            'description'       => ['nullable', 'string', 'max:2000'],
            'catalog_status'    => ['required', Rule::in(array_keys(CatalogLabels::STATUSES))],
            'category'          => ['nullable', 'string', 'max:60'],
            'target_population' => ['nullable', 'string', 'max:255'],
            'min_age'           => ['nullable', 'integer', 'between:0,120'],
            'max_age'           => ['nullable', 'integer', 'between:0,120', 'gte:min_age'],
            'max_beneficiaries' => ['nullable', 'integer', 'between:1,99'],
            'sale_from'         => ['nullable', 'date'],
            'sale_to'           => ['nullable', 'date', 'after_or_equal:sale_from'],
        ], [
            'max_age.gte' => 'L’âge maximal doit être supérieur ou égal à l’âge minimal.',
            'sale_to.after_or_equal' => 'La fin de commercialisation doit suivre son début.',
        ]);
        $guarantee->update($d);

        return redirect()->route('formulas.show', $guarantee)->with('success', 'Fiche de la formule enregistrée.');
    }

    /** Paramètres de la version (âge senior, renouvellement continu) : versionnés. */
    public function params(Request $request, Guarantee $guarantee)
    {
        $d = $request->validate([
            'senior_age'     => ['nullable', 'integer', 'between:40,120'],
            'renewal_waiver' => ['nullable', 'boolean'],
        ] + $this->versionRules($guarantee));
        $v = $this->target($guarantee, $d);
        $v->update(['senior_age' => $d['senior_age'] ?? null, 'renewal_waiver' => (bool) ($d['renewal_waiver'] ?? false)]);

        return $this->done($guarantee, $v, 'Paramètres de la version enregistrés.');
    }

    // ------------------------------------------------------------------ garanties

    public function storeRule(Request $request, Guarantee $guarantee)
    {
        $d = $this->validateRule($request, $guarantee);
        $v = $this->target($guarantee, $d);
        if ($this->duplicateRule($v, $d)) {
            return back()->withInput()->withErrors(['rule' => 'Cette formule a déjà une garantie pour cette catégorie de soins et cette catégorie de bénéficiaire : modifiez-la.']);
        }
        $v->rules()->create($this->ruleData($d) + ['sort' => (int) $v->rules()->max('sort') + 10]);

        return $this->done($guarantee, $v, 'Garantie ajoutée.');
    }

    public function updateRule(Request $request, Guarantee $guarantee, CoverageRule $rule)
    {
        $this->owned($guarantee, $rule->guarantee_version_id);
        $d = $this->validateRule($request, $guarantee);
        $v = $this->target($guarantee, $d);
        $rule = $v->rules()->where('rule_key', $rule->rule_key)->firstOrFail();
        if ($this->duplicateRule($v, $d, $rule->id)) {
            return back()->withInput()->withErrors(['rule' => 'Une autre garantie couvre déjà cette catégorie de soins pour cette catégorie de bénéficiaire.']);
        }
        $rule->update($this->ruleData($d));

        return $this->done($guarantee, $v, 'Garantie modifiée.');
    }

    /** Retirer une garantie : ses délais de carence aussi ; un plafond qui ne visait qu'elle est retiré. */
    public function destroyRule(Request $request, Guarantee $guarantee, CoverageRule $rule)
    {
        $this->owned($guarantee, $rule->guarantee_version_id);
        $d = $request->validate($this->versionRules($guarantee));
        $v = $this->target($guarantee, $d);
        $key = $rule->rule_key;
        $v->rules()->where('rule_key', $key)->delete();
        $v->waitingPeriods()->where('rule_key', $key)->delete();
        foreach ($v->limits()->get() as $l) {
            if ($l->rule_keys && in_array($key, $l->rule_keys, true)) {
                $left = array_values(array_diff($l->rule_keys, [$key]));
                // Jamais de plafond « ciblé » qui deviendrait un plafond sur TOUTES les garanties.
                $left ? $l->update(['rule_keys' => $left]) : $l->delete();
            }
        }

        return $this->done($guarantee, $v, 'Garantie retirée.');
    }

    // ------------------------------------------------------------------ plafonds

    public function storeLimit(Request $request, Guarantee $guarantee)
    {
        $d = $this->validateLimit($request, $guarantee);
        if (is_string($d)) {
            return back()->withInput()->withErrors(['limit' => $d]);
        }
        $v = $this->target($guarantee, $d);
        $v->limits()->create($this->limitData($d));

        return $this->done($guarantee, $v, 'Plafond ajouté.');
    }

    public function updateLimit(Request $request, Guarantee $guarantee, CoverageLimit $limit)
    {
        $this->owned($guarantee, $limit->guarantee_version_id);
        $d = $this->validateLimit($request, $guarantee);
        if (is_string($d)) {
            return back()->withInput()->withErrors(['limit' => $d]);
        }
        $v = $this->target($guarantee, $d);
        $this->twin($v->limits(), $limit, ['label', 'rule_keys', 'scope', 'holder', 'amount_minor', 'max_count'])?->update($this->limitData($d));

        return $this->done($guarantee, $v, 'Plafond modifié.');
    }

    public function destroyLimit(Request $request, Guarantee $guarantee, CoverageLimit $limit)
    {
        $this->owned($guarantee, $limit->guarantee_version_id);
        $v = $this->target($guarantee, $request->validate($this->versionRules($guarantee)));
        $this->twin($v->limits(), $limit, ['label', 'rule_keys', 'scope', 'holder', 'amount_minor', 'max_count'])?->delete();

        return $this->done($guarantee, $v, 'Plafond retiré.');
    }

    // ------------------------------------------------------------------ délais de carence

    public function storeWaiting(Request $request, Guarantee $guarantee)
    {
        $current = $this->current($guarantee);
        $d = $request->validate([
            'rule_key'             => ['nullable', Rule::in($current->rules()->pluck('rule_key')->all())],
            'beneficiary_category' => ['required', Rule::in(array_keys(CoverageRule::BENEFICIARY_CATEGORIES))],
            'days'                 => ['required', 'integer', 'between:0,3650'],
        ] + $this->versionRules($guarantee), ['days.required' => 'Indiquez le délai en jours (0 = aucun délai).']);
        $v = $this->target($guarantee, $d);
        $v->waitingPeriods()->updateOrCreate(
            ['rule_key' => $d['rule_key'] ?? null, 'beneficiary_category' => $d['beneficiary_category']],
            ['days' => (int) $d['days']],
        );

        return $this->done($guarantee, $v, 'Délai de carence enregistré.');
    }

    public function destroyWaiting(Request $request, Guarantee $guarantee, WaitingPeriod $waiting)
    {
        $this->owned($guarantee, $waiting->guarantee_version_id);
        $v = $this->target($guarantee, $request->validate($this->versionRules($guarantee)));
        $this->twin($v->waitingPeriods(), $waiting, ['rule_key', 'beneficiary_category', 'days'])?->delete();

        return $this->done($guarantee, $v, 'Délai de carence retiré.');
    }

    // ------------------------------------------------------------------ simulateur

    /** Simulation à partir des paramètres (aucun adhérent, rien n'est enregistré). */
    public function simulate(Request $request, Guarantee $guarantee)
    {
        $d = $request->validate([
            'version_id'  => ['required', 'integer'],
            'act_type_id' => ['required', 'integer', Rule::exists('act_types', 'id')],
            'amount'      => ['required', 'numeric', 'min:1', 'max:999999999'],
            'age'         => ['required', 'integer', 'between:0,120'],
            'days'        => ['required', 'integer', 'between:0,3650'],
            'used'        => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'used_count'  => ['nullable', 'integer', 'min:0', 'max:9999'],
            'student'     => ['nullable', 'boolean'],
            'new_spouse'  => ['nullable', 'boolean'],
            'emergency'   => ['nullable', 'boolean'],
            'accident'    => ['nullable', 'boolean'],
        ], [
            'act_type_id.required' => 'Choisissez l’acte à simuler.',
            'amount.required' => 'Indiquez le montant facturé.',
            'age.required' => 'Indiquez l’âge du bénéficiaire.',
            'days.required' => 'Indiquez l’ancienneté de la couverture en jours.',
        ]);
        $version = $guarantee->versions()->findOrFail($d['version_id']);
        $cur = $this->currency();
        $used = $cur->toMinor((float) ($d['used'] ?? 0));
        $usedCount = (int) ($d['used_count'] ?? 0);
        $r = $this->engine->compute($version, [
            'date' => now()->toDateString(),
            'act' => ActType::find($d['act_type_id']),
            'total' => $cur->toMinor((float) $d['amount']),
            'categories' => CoverageEngine::categoriesFor((int) $d['age'], $version->senior_age, (bool) ($d['student'] ?? false), (bool) ($d['new_spouse'] ?? false)),
            'coverage_start' => now()->startOfDay()->subDays((int) $d['days']),
            'emergency' => (bool) ($d['emergency'] ?? false), 'accident' => (bool) ($d['accident'] ?? false),
            'exceptions' => collect(),
            // Consommation déjà faite sur les plafonds (même valeur pour chacun) : saisie par l'utilisateur.
            'consumed' => fn () => [$used, $usedCount],
        ]);
        unset($r['version'], $r['exception']);
        $r['rule'] = $r['rule']?->label;
        $r['input'] = $d;

        return redirect()->to(route('formulas.show', $guarantee) . '#simulateur')->withInput()->with('simulation', $r);
    }

    // ------------------------------------------------------------------ bibliothèque des catégories

    public function categories()
    {
        return view('tenant.formulas.categories', [
            'categories' => ServiceCategory::withCount('acts')->orderBy('sort')->get(),
            'acts' => ActType::orderBy('category')->orderBy('label')->get(),
            'suggest' => $this->suggestions(),
        ]);
    }

    public function storeCategory(Request $request)
    {
        $d = $request->validate([
            'label'  => ['required', 'string', 'max:120', Rule::unique('service_categories', 'label')],
            'family' => ['required', Rule::in(array_keys(ServiceCategory::FAMILIES))],
        ], ['label.unique' => 'Cette catégorie existe déjà.']);
        $code = 'c' . substr(md5($d['label'] . microtime()), 0, 10);
        ServiceCategory::create(['code' => $code, 'label' => $d['label'], 'family' => $d['family'], 'sort' => (int) ServiceCategory::max('sort') + 10, 'active' => true]);

        return redirect()->route('formulas.categories')->with('success', 'Catégorie de soins ajoutée.');
    }

    /** Rattachement des actes aux catégories de soins : enregistré seulement à la validation du formulaire. */
    public function mapActs(Request $request)
    {
        $d = $request->validate([
            'map'   => ['required', 'array'],
            'map.*' => ['nullable', 'integer', Rule::exists('service_categories', 'id')],
        ]);
        $n = 0;
        foreach (ActType::whereIn('id', array_map('intval', array_keys($d['map'])))->get() as $act) {
            $cat = $d['map'][$act->id] ?? null;
            $cat = $cat === null || $cat === '' ? null : (int) $cat;
            if ($act->service_category_id !== $cat) {
                $act->update(['service_category_id' => $cat]);
                $n++;
            }
        }

        return redirect()->route('formulas.categories')->with('success', "Rattachement enregistré ({$n} acte(s) modifié(s)).");
    }

    // ------------------------------------------------------------------ formules d'exemple (MUTUELLE TEST)

    public function examples()
    {
        abort_unless(TestEnvironment::active(), 403, 'Les formules d’exemple ne sont disponibles que dans la mutuelle de test.');
        $n = app(\App\Services\Tenant\ExampleFormulaService::class)->load();

        return redirect()->route('formulas.index')->with('success', $n
            ? "{$n} formule(s) d’exemple chargée(s), marquées « Exemple »."
            : 'Les formules d’exemple sont déjà chargées.');
    }

    // ------------------------------------------------------------------ outils

    private function current(Guarantee $guarantee): GuaranteeVersion
    {
        return $guarantee->versions()->whereNull('valid_to')->latest('version_no')->firstOrFail();
    }

    /** La ligne modifiée doit appartenir à la version EN COURS de cette formule. */
    private function owned(Guarantee $guarantee, int $versionId): void
    {
        abort_unless($versionId === $this->current($guarantee)->id, 404);
    }

    /** Date d'effet exigée quand la version en cours est déjà utilisée (verrouillée). */
    private function versionRules(Guarantee $guarantee): array
    {
        $current = $this->current($guarantee);

        return [
            'effective_from' => $current->locked
                ? ['required', 'date', 'after_or_equal:today', 'after:' . $current->valid_from->toDateString()] : ['nullable'],
            'change_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function target(Guarantee $guarantee, array $d): GuaranteeVersion
    {
        return $this->versioning->editableForCatalog($guarantee, $d['effective_from'] ?? null, $d['change_note'] ?? null);
    }

    private function done(Guarantee $guarantee, GuaranteeVersion $v, string $msg)
    {
        $wasNew = $v->wasRecentlyCreated;

        return redirect()->route('formulas.show', $guarantee)->with('success', $wasNew
            ? "{$msg} Enregistré dans la nouvelle version V{$v->version_no}, applicable à partir du {$v->valid_from->format('d/m/Y')} : la version précédente, déjà utilisée, reste inchangée pour les soins déjà réalisés."
            : $msg);
    }

    /** Ligne équivalente dans la version cible (la même si elle n'a pas changé, sa copie sinon). */
    private function twin($relation, $model, array $fields)
    {
        if ((int) $relation->getParent()->id === (int) $model->guarantee_version_id) {
            return $model;
        }

        return $relation->get()->first(function ($row) use ($model, $fields) {
            foreach ($fields as $f) {
                if ($row->{$f} != $model->{$f}) {
                    return false;
                }
            }

            return true;
        });
    }

    private function validateRule(Request $request, Guarantee $guarantee): array
    {
        return $request->validate([
            'service_category_id'   => ['nullable', 'integer', Rule::exists('service_categories', 'id')],
            'label'                 => ['required', 'string', 'max:120'],
            'description'           => ['nullable', 'string', 'max:2000'],
            'beneficiary_category'  => ['required', Rule::in(array_keys(CoverageRule::BENEFICIARY_CATEGORIES))],
            'coverage_rate'         => ['required', 'numeric', 'between:0,100'],
            'copay_rate'            => ['nullable', 'numeric', 'between:0,100'],
            'accident_waiting_days' => ['nullable', 'integer', 'between:0,3650'],
            'emergency_exempt'      => ['nullable', 'boolean'],
            'tariff_cap'            => ['nullable', 'boolean'],
            'active'                => ['nullable', 'boolean'],
            'conditions'            => ['nullable', 'string', 'max:2000'],
        ] + $this->versionRules($guarantee), [
            'label.required' => 'Donnez un nom à la garantie (ex. « Consultations »).',
            'coverage_rate.required' => 'Indiquez le taux de prise en charge : aucun taux n’est appliqué par défaut.',
            'coverage_rate.between' => 'Le taux de prise en charge est compris entre 0 et 100 %.',
        ]);
    }

    private function ruleData(array $d): array
    {
        return [
            'service_category_id' => $d['service_category_id'] ?? null, 'label' => $d['label'], 'description' => $d['description'] ?? null,
            'beneficiary_category' => $d['beneficiary_category'], 'coverage_rate' => $d['coverage_rate'],
            'copay_rate' => $d['copay_rate'] ?? null, 'accident_waiting_days' => $d['accident_waiting_days'] ?? null,
            'emergency_exempt' => (bool) ($d['emergency_exempt'] ?? false), 'tariff_cap' => (bool) ($d['tariff_cap'] ?? false),
            'active' => (bool) ($d['active'] ?? false), 'conditions' => $d['conditions'] ?? null,
        ];
    }

    private function duplicateRule(GuaranteeVersion $v, array $d, ?int $except = null): bool
    {
        return $v->rules()->where('beneficiary_category', $d['beneficiary_category'])
            ->when($d['service_category_id'] ?? null, fn ($q, $c) => $q->where('service_category_id', $c), fn ($q) => $q->whereNull('service_category_id'))
            ->when($except, fn ($q) => $q->where('id', '!=', $except))->exists();
    }

    /** @return array|string données validées, ou message d'erreur métier */
    private function validateLimit(Request $request, Guarantee $guarantee): array|string
    {
        $keys = $this->current($guarantee)->rules()->pluck('rule_key')->all();
        $d = $request->validate([
            'label'       => ['nullable', 'string', 'max:120'],
            'rule_keys'   => ['nullable', 'array'],
            'rule_keys.*' => [Rule::in($keys)],
            'scope'       => ['required', Rule::in(array_keys(CoverageLimit::SCOPES))],
            'holder'      => ['required', Rule::in(array_keys(CoverageLimit::HOLDERS))],
            'amount'      => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'max_count'   => ['nullable', 'integer', 'min:1', 'max:9999'],
            'active'      => ['nullable', 'boolean'],
        ] + $this->versionRules($guarantee));
        $hasAmount = ($d['amount'] ?? null) !== null && $d['amount'] !== '';
        if (! $hasAmount && empty($d['max_count'])) {
            return 'Indiquez un montant maximal, un nombre maximal d’actes, ou les deux.';
        }
        if ($d['scope'] === 'act' && ! $hasAmount) {
            return 'Un plafond « par acte » se fixe en montant (le nombre d’actes se compte par jour, mois ou année).';
        }

        return $d;
    }

    private function limitData(array $d): array
    {
        $hasAmount = ($d['amount'] ?? null) !== null && $d['amount'] !== '';

        return [
            'label' => $d['label'] ?? null, 'rule_keys' => array_values(array_unique($d['rule_keys'] ?? [])) ?: null,
            'scope' => $d['scope'], 'holder' => $d['holder'],
            'amount_minor' => $hasAmount ? $this->currency()->toMinor((float) $d['amount']) : null,
            'max_count' => $d['max_count'] ?? null, 'active' => (bool) ($d['active'] ?? false),
        ];
    }

    /**
     * Proposition de rattachement d'un acte à une catégorie de soins (d'après son code de la liste de
     * référence, sinon sa catégorie libre). Simple pré-sélection du formulaire : rien n'est enregistré
     * tant que l'administrateur ne valide pas.
     */
    private function suggestions(): array
    {
        $ids = ServiceCategory::pluck('id', 'code');
        $byPrefix = ['CS-SPE' => 'consultation_specialist', 'CS-URG' => 'emergency', 'CS-PED' => 'consultation', 'CS-' => 'consultation',
            'MAT-CPN' => 'prenatal', 'MAT-ACC' => 'delivery_simple', 'MAT-DYS' => 'delivery_complicated', 'MAT-CES' => 'cesarean', 'MAT-' => 'prenatal', 'ENF-' => 'consultation',
            'HOS-' => 'hospitalization', 'LAB-' => 'lab_tests', 'IMG-RX' => 'radiology', 'IMG-ECHO' => 'ultrasound', 'IMG-SCAN' => 'ct_scan', 'IMG-' => 'imaging',
            'CHI-PET' => 'minor_surgery', 'CHI-' => 'major_surgery', 'DEN-' => 'dental', 'OPH-' => 'optical', 'PHA-' => 'pharmacy', 'TRA-' => 'transport', 'EVA-' => 'transport'];
        $byLabel = ['consultation' => 'consultation', 'pharmacie' => 'pharmacy', 'hospitalisation' => 'hospitalization', 'analyse' => 'lab_tests',
            'laboratoire' => 'lab_tests', 'imagerie' => 'imaging', 'dentaire' => 'dental', 'optique' => 'optical', 'chirurgie' => 'major_surgery', 'maternit' => 'prenatal', 'urgence' => 'emergency'];
        $out = [];
        foreach (ActType::whereNull('service_category_id')->get() as $a) {
            $code = null;
            foreach ($byPrefix as $p => $c) {
                if (str_starts_with((string) $a->code, $p)) {
                    $code = $c;
                    break;
                }
            }
            if (! $code) {
                $hay = mb_strtolower($a->category . ' ' . $a->label);
                foreach ($byLabel as $needle => $c) {
                    if (str_contains($hay, $needle)) {
                        $code = $c;
                        break;
                    }
                }
            }
            if ($code && isset($ids[$code])) {
                $out[$a->id] = $ids[$code];
            }
        }

        return $out;
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
