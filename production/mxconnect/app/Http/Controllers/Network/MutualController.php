<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMutualRequest;
use App\Models\Central\Country;
use App\Models\Central\Currency;
use App\Models\Central\Locale;
use App\Models\Central\Mutual;
use App\Services\MutualProvisioningService;
use Illuminate\Http\Request;

class MutualController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Mutual::class);

        // Les pages créées par les membres du réseau social se gèrent dans « Modération ».
        $base = fn () => Mutual::query()->whereDoesntHave('profile', fn ($p) => $p->whereNotNull('owner_account_id'));
        $status = in_array($request->get('statut'), ['pending', 'approved', 'suspended'], true) ? $request->get('statut') : null;
        $q = trim((string) $request->get('q'));

        $mutuals = $base()->with(['country', 'currency', 'domains'])
            ->when($status, fn ($w) => $w->where('status', $status))
            ->when($request->integer('pays'), fn ($w, $c) => $w->where('country_id', $c))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%")->orWhere('city', 'like', "%{$q}%")))
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'suspended')")->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('network.mutuals.index', [
            'mutuals'   => $mutuals,
            'counts'    => $base()->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'countries' => Country::whereIn('id', $base()->select('country_id'))->orderBy('name')->get(['id', 'name']),
            'status'    => $status,
        ]);
    }

    /** Fiche d'une mutuelle : identité, espace, stockage, état et historique, accès administrateur. */
    public function show(Mutual $mutual)
    {
        $this->authorize('view', $mutual);
        abort_if($mutual->isMemberPage(), 404);
        $mutual->load(['country', 'currency', 'domains']);
        $admins = collect();
        try {
            // Valeurs simples lues DANS le contexte de la mutuelle (les dates d'un modèle ont besoin de sa connexion).
            $admins = $mutual->run(fn () => \App\Models\Tenant\User::role('mutual_admin')->orderBy('first_name')->get()
                ->map(fn ($a) => (object) ['name' => trim($a->first_name . ' ' . $a->last_name), 'email' => $a->email, 'active' => (bool) $a->active,
                    'mfa' => (bool) $a->two_factor_confirmed_at, 'last_login' => $a->last_login_at?->format('d/m/Y')]));
        } catch (\Throwable $e) {
            report($e);   // base inaccessible : la fiche reste consultable
        }
        $byId = \App\Models\Central\NetworkUser::whereIn('id', collect($mutual->status_history ?? [])->pluck('by')->filter()->unique())->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);

        return view('network.mutuals.show', [
            'mutual'  => $mutual,
            'locale'  => Locale::find($mutual->locale_id),
            'admins'  => $admins,
            'history' => collect($mutual->status_history ?? [])->reverse()->values(),
            'byId'    => $byId,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Mutual::class);

        return view('network.mutuals.create', [
            'countries'  => Country::where('active', true)->orderBy('name')->get(),
            'currencies' => Currency::where('active', true)->orderBy('code')->get(),
            'locales'    => Locale::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreMutualRequest $request, MutualProvisioningService $provisioner)
    {
        // Creating the Mutual fires the tenancy pipeline: create DB -> migrate -> seed.
        $data = $request->validated();
        $admin = array_filter([
            'email' => $data['admin_email'] ?? null,
            'first' => $data['admin_first_name'] ?? null,
            'last'  => $data['admin_last_name'] ?? null,
        ]);
        unset($data['admin_email'], $data['admin_first_name'], $data['admin_last_name']);

        try {
            $mutual = $provisioner->create($data);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput($request->except('db_password'))
                ->withErrors(['provisioning' => 'Création impossible : ' . $e->getMessage()]);
        }

        $redirect = redirect()->route('network.mutuals.show', $mutual)
            ->with('success', "Mutuelle « {$mutual->name} » créée : son espace, ses tables, ses rôles et son plan comptable sont prêts. Elle reste en attente de votre agrément.");

        if (! empty($admin['email'])) {
            try {
                $password = $provisioner->createUser($mutual, $admin['email'],
                    $admin['first'] ?? 'Admin', $admin['last'] ?? $mutual->name);
                $domain = optional($mutual->domains()->first())->domain;
                $redirect->with('admin_password', [mb_strtolower(trim($admin['email'])), $password, "https://{$domain}/connexion", false]);
            } catch (\Throwable $e) {
                report($e);
                $redirect->with('error', "Mutuelle créée, mais l'administrateur n'a pas pu l'être : " . $e->getMessage());
            }
        }

        return $redirect;
    }

    public function approve(Request $request, Mutual $mutual, MutualProvisioningService $provisioner)
    {
        $this->authorize('approve', $mutual);

        $provisioner->approve($mutual, $request->user('network')->id);

        return back()->with('success', __('mxconnect.mutual.approved', ['name' => $mutual->name]));
    }

    public function suspend(Request $request, Mutual $mutual, MutualProvisioningService $provisioner)
    {
        $this->authorize('suspend', $mutual);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']],
            ['reason.required' => 'Indiquez le motif de la suspension (il est conservé dans l’historique).', 'reason.min' => 'Précisez le motif de la suspension.']);

        $provisioner->suspend($mutual, trim($data['reason']), $request->user('network')->id);

        return back()->with('success', "{$mutual->name} est suspendue : son espace de gestion, le portail des prestataires et l’espace des membres sont fermés. Les données sont conservées.");
    }

    public function reactivate(Request $request, Mutual $mutual, MutualProvisioningService $provisioner)
    {
        $this->authorize('reactivate', $mutual);
        $provisioner->reactivate($mutual, $request->user('network')->id);

        return back()->with('success', "{$mutual->name} est réactivée : son espace est de nouveau ouvert.");
    }

    /**
     * Créer un administrateur de la mutuelle, ou rétablir l'accès d'un administrateur existant
     * (nouveau mot de passe provisoire affiché une fois, double authentification à reconfigurer).
     */
    public function admin(Request $request, Mutual $mutual, MutualProvisioningService $provisioner)
    {
        $this->authorize('manageAccess', $mutual);
        $data = $request->validate([
            'admin_email'      => ['required', 'email', 'max:180'],
            'admin_first_name' => ['nullable', 'string', 'max:120'],
            'admin_last_name'  => ['nullable', 'string', 'max:120'],
        ]);
        $email = mb_strtolower(trim($data['admin_email']));
        $existing = $mutual->run(fn () => \App\Models\Tenant\User::withTrashed()->where('email', $email)->first());
        $password = $provisioner->createUser($mutual, $email, ($data['admin_first_name'] ?? '') ?: ($existing->first_name ?? 'Admin'),
            ($data['admin_last_name'] ?? '') ?: ($existing->last_name ?? $mutual->name));
        $mutual->run(fn () => \App\Models\Tenant\User::where('email', $email)
            ->update(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null]));
        $domain = optional($mutual->domains()->first())->domain;

        return back()->with('admin_password', [$email, $password, "https://{$domain}/connexion", (bool) $existing])
            ->with('success', $existing ? "Accès de {$email} rétabli." : "Administrateur {$email} créé.");
    }
}
