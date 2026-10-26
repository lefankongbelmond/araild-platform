<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\CareProvider;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ProviderConvention;
use App\Models\Tenant\ProviderInvoice;
use App\Models\Tenant\RightsCheck;
use App\Models\Tenant\User;
use App\Support\TenantAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * « Prestataires conventionnés » (Paramètres). La fiche du prestataire est dans le
 * registre CENTRAL (care_providers, partagé par les mutuelles) ; la convention
 * (numéro, dates, contact, actif) est propre à la mutuelle. Une mutuelle ne modifie que
 * les fiches qu'elle a créées. Lecture : administrateur, contrôleur, gestionnaire des
 * prestations ; gestion : administrateur (matrice App\Support\TenantAccess).
 */
class ProviderController extends Controller
{
    public function index()
    {
        $conventions = ProviderConvention::orderByDesc('active')->get();
        $providers = CareProvider::on('central')->whereIn('id', $conventions->pluck('care_provider_id'))->get()->keyBy('id');
        $accounts = User::whereNotNull('care_provider_id')->where('active', true)
            ->selectRaw('care_provider_id, count(*) n')->groupBy('care_provider_id')->pluck('n', 'care_provider_id');
        $pending = Prestation::where('status', 'captured')->selectRaw('provider_id, count(*) n')->groupBy('provider_id')->pluck('n', 'provider_id');

        return view('tenant.providers.index', compact('conventions', 'providers', 'accounts', 'pending'));
    }

    public function create()
    {
        return view('tenant.providers.form', ['convention' => new ProviderConvention(['active' => true]), 'provider' => null, 'available' => $this->available()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'mode'             => ['required', 'in:existing,new'],
            'care_provider_id' => ['exclude_unless:mode,existing', 'required', 'integer'],
        ] + $this->providerRules('exclude_unless:mode,new'), $this->messages());

        $convention = DB::transaction(function () use ($data, $request) {
            if ($data['mode'] === 'existing') {
                $provider = $this->available()->firstWhere('id', (int) $data['care_provider_id']);
                abort_unless($provider, 422, 'Prestataire introuvable ou déjà conventionné.');
            } else {
                $provider = CareProvider::on('central')->create($this->providerData($data) + [
                    'country_id' => tenant('country_id'), 'active' => true, 'created_by_mutual' => (string) tenant('id'),
                ]);
            }

            return ProviderConvention::create($this->conventionData($data) + ['care_provider_id' => $provider->id, 'created_by' => $request->user()->id]);
        });

        return redirect()->route('providers.show', $convention->id)->with('success', 'Prestataire conventionné ajouté. Vous pouvez maintenant créer ses comptes dans « Équipe et rôles » (rôle « Prestataire de soins »).');
    }

    public function show(int $convention)
    {
        $c = ProviderConvention::findOrFail($convention);
        $provider = $c->provider();

        return view('tenant.providers.show', [
            'c'        => $c,
            'provider' => $provider,
            'accounts' => User::where('care_provider_id', $c->care_provider_id)->orderByDesc('active')->get(),
            'stats'    => Prestation::where('provider_id', $c->care_provider_id)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'invoices' => ProviderInvoice::where('provider_id', $c->care_provider_id)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'checks'   => RightsCheck::where('care_provider_id', $c->care_provider_id)->where('created_at', '>=', now()->subDays(30))->count(),
            'canEdit'  => TenantAccess::can('providers.update'),
            // Garanties dont la version en cours accepte cet établissement (réseau vide = tous).
            'guarantees' => \App\Models\Tenant\GuaranteeVersion::with('guarantee')->whereNull('valid_to')->get()
                ->filter(fn ($v) => $v->guarantee && (! is_array($v->provider_network) || in_array($c->care_provider_id, $v->provider_network, true)))
                ->map(fn ($v) => $v->guarantee->name . (is_array($v->provider_network) ? '' : ' (réseau ouvert à tous)'))->values(),
        ]);
    }

    public function edit(int $convention)
    {
        $c = ProviderConvention::findOrFail($convention);

        return view('tenant.providers.form', ['convention' => $c, 'provider' => $c->provider(), 'available' => collect()]);
    }

    public function update(Request $request, int $convention)
    {
        $c = ProviderConvention::findOrFail($convention);
        $provider = $c->provider();
        $own = $provider && $provider->created_by_mutual === (string) tenant('id');
        $data = $request->validate($this->rules() + ($own ? $this->providerRules('') : []), $this->messages());

        DB::transaction(function () use ($c, $provider, $own, $data) {
            $c->update($this->conventionData($data));
            if ($own) {
                $provider->update($this->providerData($data));   // fiche créée par cette mutuelle
            }
        });

        return redirect()->route('providers.show', $c->id)->with('success', 'Convention mise à jour.');
    }

    public function toggle(int $convention)
    {
        $c = ProviderConvention::findOrFail($convention);
        $c->update(['active' => ! $c->active]);

        return back()->with('success', $c->active
            ? 'Convention réactivée : le portail du prestataire est de nouveau ouvert.'
            : 'Convention suspendue : les comptes de ce prestataire ne peuvent plus vérifier de droits ni déclarer de soins.');
    }

    // ---

    /** Prestataires actifs du registre (pays de la mutuelle) pas encore conventionnés. */
    private function available()
    {
        return CareProvider::on('central')->where('active', true)
            ->where(fn ($q) => $q->where('country_id', tenant('country_id'))->orWhereNull('country_id'))
            ->whereNotIn('id', ProviderConvention::pluck('care_provider_id'))
            ->orderBy('name')->get();
    }

    private function rules(): array
    {
        return [
            'reference'     => ['nullable', 'string', 'max:60'],
            'starts_on'     => ['nullable', 'date'],
            'ends_on'       => ['nullable', 'date', 'after_or_equal:starts_on'],
            'contact_name'  => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function providerRules(string $when): array
    {
        $w = $when ? [$when] : [];

        return [
            'name'     => [...$w, 'required', 'string', 'max:180'],
            'type'     => [...$w, 'required', Rule::in(array_keys(ProviderConvention::TYPES))],
            'locality' => [...$w, 'nullable', 'string', 'max:120'],
            'phone'    => [...$w, 'nullable', 'string', 'max:30'],
        ];
    }

    private function messages(): array
    {
        return [
            'care_provider_id.required' => 'Choisissez un prestataire du registre, ou créez-en un nouveau.',
            'name.required'             => 'Indiquez le nom du prestataire.',
            'ends_on.after_or_equal'    => 'La fin de la convention doit suivre son début.',
        ];
    }

    private function conventionData(array $d): array
    {
        return collect($d)->only(['reference', 'starts_on', 'ends_on', 'contact_name', 'contact_phone', 'notes'])->all();
    }

    private function providerData(array $d): array
    {
        return ['name' => trim($d['name']), 'type' => $d['type'], 'locality' => $d['locality'] ?? null, 'phone' => $d['phone'] ?? null];
    }
}
