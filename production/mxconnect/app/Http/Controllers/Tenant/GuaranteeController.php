<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuaranteeRequest;
use App\Http\Requests\GuaranteeVersionRequest;
use App\Models\Central\Currency;
use App\Models\Tenant\Guarantee;
use App\Models\Central\CareProvider;
use App\Models\Tenant\ProviderConvention;
use App\Services\GuaranteeVersioningService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuaranteeController extends Controller
{
    public function __construct(private GuaranteeVersioningService $versioning) {}

    public function index()
    {
        return view('tenant.guarantees.index', [
            'guarantees' => Guarantee::with('currentVersion')->orderBy('name')->get(),
            'currency'   => $this->currency(),
        ]);
    }

    public function create()
    {
        return view('tenant.guarantees.create', ['currency' => $this->currency()]);
    }

    public function store(GuaranteeRequest $request)
    {
        $d = $request->validated();
        $cur = $this->currency();

        $this->versioning->createWithFirstVersion(
            guarantee: [
                'code' => $d['code'], 'name' => $d['name'],
                'description' => $d['description'] ?? null, 'status' => 'active',
            ],
            version: [
                'base_contribution_minor' => $cur->toMinor((float) $d['base_contribution']),
                'periodicity'             => $d['periodicity'],
                'coverage_rate'           => $d['coverage_rate'],
                'copay_rate'              => $d['copay_rate'],
                'membership_fee_minor'    => $cur->toMinor((float) ($d['membership_fee'] ?? 0)),
                'observation_days'        => $d['observation_days'],
                'valid_from'              => $d['valid_from'] ?? now()->toDateString(),
            ],
        );

        return redirect()->route('guarantees.index')->with('success', __('mxconnect.guarantee.created'));
    }

    public function show(Guarantee $guarantee)
    {
        $guarantee->load(['versions' => fn ($q) => $q->orderByDesc('version_no'), 'versions.ceilings.actType']);
        $ids = $guarantee->versions->pluck('provider_network')->filter()->flatten()
            ->merge(\Illuminate\Support\Facades\Schema::hasTable('provider_conventions') ? ProviderConvention::pluck('care_provider_id') : [])->unique();

        return view('tenant.guarantees.show', [
            'guarantee'   => $guarantee,
            'currency'    => $this->currency(),
            'providers'   => CareProvider::on('central')->whereIn('id', $ids)->orderBy('name')->pluck('name', 'id'),
            'acts'        => \App\Models\Tenant\ActType::orderBy('label')->get(['id', 'label', 'category']),
            'conventions' => \Illuminate\Support\Facades\Schema::hasTable('provider_conventions')
                ? ProviderConvention::all()->keyBy('care_provider_id') : collect(),
        ]);
    }

    /**
     * Réseau de soins de la garantie : tous les prestataires, ou une liste de prestataires
     * conventionnés. Version déjà utilisée (verrouillée) : une nouvelle version s'ouvre à la
     * date choisie, les conditions financières sont reprises à l'identique.
     */
    public function network(Request $request, Guarantee $guarantee)
    {
        $current = $guarantee->versions()->whereNull('valid_to')->latest('version_no')->firstOrFail();
        $conventioned = ProviderConvention::pluck('care_provider_id')->all();
        $data = $request->validate([
            'mode'           => ['required', 'in:all,list'],
            'providers'      => ['exclude_unless:mode,list', 'required', 'array', 'min:1'],
            'providers.*'    => ['integer', Rule::in($conventioned)],
            'effective_from' => $current->locked
                ? ['required', 'date', 'after_or_equal:today', 'after:' . $current->valid_from->toDateString()] : ['nullable'],
        ], [
            'providers.required'  => 'Cochez au moins un prestataire, ou choisissez « Tous les prestataires ».',
            'providers.*.in'      => 'Seuls les prestataires conventionnés peuvent faire partie du réseau.',
            'effective_from.after' => 'La nouvelle version doit commencer après le début de la version en cours.',
        ]);

        $version = $this->versioning->setNetwork($guarantee, $data['mode'] === 'list' ? $data['providers'] : null, $data['effective_from'] ?? null);

        return redirect()->route('guarantees.show', $guarantee)->with('success', $version->is($current)
            ? 'Réseau de soins mis à jour.'
            : "Réseau de soins enregistré dans la version V{$version->version_no}, applicable à partir du {$version->valid_from->format('d/m/Y')} (la version précédente, déjà utilisée par des recours, reste inchangée).");
    }

    /** Roll to a new version — closes the current one, opens the successor. */
    public function newVersion(GuaranteeVersionRequest $request, Guarantee $guarantee)
    {
        $d = $request->validated();
        $cur = $this->currency();

        $this->versioning->newVersion($guarantee, [
            'base_contribution_minor' => $cur->toMinor((float) $d['base_contribution']),
            'periodicity'             => $d['periodicity'],
            'coverage_rate'           => $d['coverage_rate'],
            'copay_rate'              => $d['copay_rate'],
            'membership_fee_minor'    => $cur->toMinor((float) ($d['membership_fee'] ?? 0)),
            'observation_days'        => $d['observation_days'],
        ], effectiveFrom: $d['effective_from']);

        return redirect()->route('guarantees.show', $guarantee)
            ->with('success', __('mxconnect.guarantee.version_created'));
    }

    /**
     * Plafonds et exclusions de la garantie (2026-11-03). Montants saisis dans la devise de la
     * mutuelle ; un plafond laissé vide est supprimé. Version déjà utilisée → nouvelle version datée.
     */
    public function terms(Request $request, Guarantee $guarantee)
    {
        $current = $guarantee->versions()->whereNull('valid_to')->latest('version_no')->firstOrFail();
        $data = $request->validate([
            'exclusions'               => ['nullable', 'array'],
            'exclusions.*'             => ['integer', Rule::exists('act_types', 'id')],
            'ceilings'                 => ['nullable', 'array', 'max:60'],
            'ceilings.*.act_type_id'   => ['nullable', 'integer', Rule::exists('act_types', 'id')],
            'ceilings.*.amount'        => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'ceilings.*.period'        => ['required_with:ceilings.*.amount', 'nullable', Rule::in(array_keys(\App\Models\Tenant\Ceiling::PERIODS))],
            'effective_from'           => $current->locked
                ? ['required', 'date', 'after_or_equal:today', 'after:' . $current->valid_from->toDateString()] : ['nullable'],
        ], [
            'ceilings.*.amount.numeric' => 'Le montant d’un plafond doit être un nombre.',
            'effective_from.after'      => 'La nouvelle version doit commencer après le début de la version en cours.',
        ]);

        $cur = $this->currency();
        $ceilings = [];
        foreach ($data['ceilings'] ?? [] as $c) {
            if (($c['amount'] ?? null) === null || $c['amount'] === '') {
                continue;   // ligne vide = pas de plafond
            }
            $key = ($c['act_type_id'] ?? 0) . '|' . $c['period'];
            if (isset($ceilings[$key])) {
                return back()->withInput()->withErrors(['ceilings' => 'Deux plafonds identiques (même acte, même période) : gardez-en un seul.']);
            }
            $ceilings[$key] = ['act_type_id' => isset($c['act_type_id']) ? (int) $c['act_type_id'] : null,
                'ceiling_minor' => $cur->toMinor((float) $c['amount']), 'period' => $c['period']];
        }

        $version = $this->versioning->setTerms($guarantee, $data['exclusions'] ?? [], array_values($ceilings), $data['effective_from'] ?? null);

        return redirect()->route('guarantees.show', $guarantee)->with('success', $version->is($current)
            ? 'Plafonds et exclusions enregistrés.'
            : "Plafonds et exclusions enregistrés dans la version V{$version->version_no}, applicable à partir du {$version->valid_from->format('d/m/Y')} (la version précédente, déjà utilisée par des recours, reste inchangée).");
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
