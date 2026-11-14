<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\CareProvider;
use App\Models\Provider\ProviderAccount;
use App\Models\Provider\ProviderAffiliation;
use App\Models\Provider\ProviderMembership;
use App\Services\Provider\ProviderSpaceService;
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

        // Demandes d'affiliation envoyées depuis l'espace prestataire central (2026-11-16).
        $requests = \Illuminate\Support\Facades\Schema::connection('central')->hasTable('provider_affiliations')
            ? ProviderAffiliation::with('provider')->where('mutual_id', (string) tenant('id'))->where('status', 'requested')->oldest()->get()
            : collect();
        // Personnes de l'espace prestataire, par établissement (comptes centraux actifs).
        $spaceStaff = \Illuminate\Support\Facades\Schema::connection('central')->hasTable('provider_memberships')
            ? ProviderMembership::whereIn('care_provider_id', $conventions->pluck('care_provider_id'))->where('active', true)
                ->selectRaw('care_provider_id, count(*) n')->groupBy('care_provider_id')->pluck('n', 'care_provider_id')
            : collect();

        return view('tenant.providers.index', compact('conventions', 'providers', 'accounts', 'pending', 'requests', 'spaceStaff'));
    }

    /** Accepter une demande d'affiliation : la convention est créée dans la base de la mutuelle. */
    public function approveAffiliation(Request $request, int $affiliation)
    {
        $aff = ProviderAffiliation::where('mutual_id', (string) tenant('id'))->where('status', 'requested')->findOrFail($affiliation);
        abort_if(ProviderConvention::where('care_provider_id', $aff->care_provider_id)->exists(), 422, 'Cet établissement est déjà conventionné.');
        $data = $request->validate($this->rules(), $this->messages());

        $c = DB::transaction(fn () => ProviderConvention::create($this->conventionData($data) + [
            'care_provider_id' => $aff->care_provider_id, 'active' => true, 'created_by' => $request->user()->id,
        ]));
        $aff->decision_note = null;
        $aff->save();
        ProviderSpaceService::syncFromConvention($c, 'Demande acceptée, convention créée', $request->user()->name);

        return redirect()->route('providers.show', $c->id)->with('success', 'Demande acceptée : la convention est créée. Le personnel de l’établissement peut désormais ouvrir votre portail depuis son espace prestataire.');
    }

    public function rejectAffiliation(Request $request, int $affiliation)
    {
        $aff = ProviderAffiliation::where('mutual_id', (string) tenant('id'))->where('status', 'requested')->findOrFail($affiliation);
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:500']], ['note.required' => 'Indiquez le motif du refus : il est communiqué à l’établissement.', 'note.min' => 'Précisez le motif du refus.']);
        $aff->fill(['status' => 'rejected', 'decision_note' => trim($data['note']), 'decided_at' => now()]);
        $aff->log('Demande refusée', $request->user()->name, trim($data['note']));
        $aff->save();

        return back()->with('success', 'Demande refusée ; le motif est communiqué à l’établissement.');
    }

    /**
     * Ouvrir l'espace prestataire du responsable d'un établissement conventionné qui n'a
     * encore aucun administrateur actif (établissement ajouté par la mutuelle au registre).
     */
    public function grantAccess(Request $request, int $convention)
    {
        $c = ProviderConvention::findOrFail($convention);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'], 'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:180'], 'phone' => ['nullable', 'string', 'max:30'],
        ], ['email.required' => 'Indiquez l’adresse e-mail du responsable (elle sert d’identifiant).']);
        if (ProviderMembership::where('care_provider_id', $c->care_provider_id)->where('role', 'admin')->where('active', true)->exists()) {
            return back()->with('error', 'Cet établissement a déjà un administrateur dans l’espace prestataire : c’est lui qui ajoute son personnel.');
        }
        $email = \Illuminate\Support\Str::lower(trim($data['email']));
        $account = ProviderAccount::where('email', $email)->first();
        $temp = null;
        DB::connection('central')->transaction(function () use (&$account, &$temp, $data, $email, $c) {
            if (! $account) {
                $temp = \App\Http\Controllers\Provider\EstablishmentController::temporaryPassword();
                $account = ProviderAccount::create(['first_name' => trim($data['first_name']), 'last_name' => trim($data['last_name']), 'email' => $email,
                    'phone' => $data['phone'] ?? null, 'password' => $temp, 'must_change_password' => true, 'active' => true]);
            }
            ProviderMembership::updateOrCreate(['provider_account_id' => $account->id, 'care_provider_id' => $c->care_provider_id], ['role' => 'admin', 'active' => true]);
        });
        ProviderSpaceService::syncFromConvention($c, 'Accès du responsable ouvert', $request->user()->name);

        return back()->with('success', $temp
            ? 'Espace prestataire ouvert pour le responsable. Transmettez-lui l’adresse et le mot de passe provisoire : il ne sera plus affiché.'
            : 'Cette personne avait déjà un compte prestataire : elle devient administratrice de l’établissement avec son mot de passe habituel.')
            ->with('temp_password', $temp ? ['email' => $email, 'password' => $temp, 'url' => rtrim((string) config('app.url'), '/') . '/espace-prestataire'] : null);
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
        ProviderSpaceService::syncFromConvention($convention, 'Convention créée par la mutuelle', $request->user()->name);

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
            // Personnes de l'espace prestataire central rattachées à cet établissement (2026-11-16).
            'spaceMembers' => \Illuminate\Support\Facades\Schema::connection('central')->hasTable('provider_memberships')
                ? ProviderMembership::with('account')->where('care_provider_id', $c->care_provider_id)->orderByDesc('active')->orderBy('role')->get()
                : collect(),
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

        DB::transaction(function () use ($c, $provider, $own, $data, $request) {
            $c->fill($this->conventionData($data));
            $this->logHistory($c, 'Convention modifiée', $request->user()->name);
            $c->save();
            if ($own) {
                $provider->update($this->providerData($data));   // fiche créée par cette mutuelle
            }
        });

        ProviderSpaceService::syncFromConvention($c, 'Convention modifiée', $request->user()->name);

        return redirect()->route('providers.show', $c->id)->with('success', 'Convention mise à jour.');
    }

    public function toggle(Request $request, int $convention)
    {
        $c = ProviderConvention::findOrFail($convention);
        $c->active = ! $c->active;
        $this->logHistory($c, $c->active ? 'Convention réactivée' : 'Convention suspendue', $request->user()->name);
        $c->save();
        ProviderSpaceService::syncFromConvention($c, $c->active ? 'Convention réactivée' : 'Convention suspendue', $request->user()->name);

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
            'signed_on'     => ['nullable', 'date'],
            'transmission_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'payment_days'  => ['nullable', 'integer', 'min:1', 'max:365'],
            'billing_terms' => ['nullable', 'string', 'max:2000'],
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
        $keys = ['reference', 'starts_on', 'ends_on', 'contact_name', 'contact_phone', 'notes'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('provider_conventions', 'signed_on')) {
            $keys = [...$keys, 'signed_on', 'transmission_days', 'payment_days', 'billing_terms'];
        }

        return collect($d)->only($keys)->all();
    }

    /** Historique des modifications de la convention (qui, quoi, quand ; champs modifiés). */
    private function logHistory(ProviderConvention $c, string $event, ?string $who): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('provider_conventions', 'history')) {
            return;
        }
        $changed = array_keys(array_diff_key($c->getDirty(), ['history' => 1]));
        $h = (array) ($c->history ?? []);
        $h[] = array_filter(['at' => now()->toIso8601String(), 'event' => $event, 'by' => $who, 'fields' => $changed ?: null]);
        $c->history = array_slice($h, -50);
    }

    private function providerData(array $d): array
    {
        return ['name' => trim($d['name']), 'type' => $d['type'], 'locality' => $d['locality'] ?? null, 'phone' => $d['phone'] ?? null];
    }
}
