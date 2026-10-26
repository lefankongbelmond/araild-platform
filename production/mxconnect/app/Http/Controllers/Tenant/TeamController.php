<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Antenna;
use App\Models\Tenant\User;
use App\Services\Tenant\TeamService;
use App\Support\TenantNav;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * « Équipe et rôles » : le personnel de la mutuelle (base de la mutuelle) et ses droits.
 * Réservé à l'administrateur. Les mots de passe provisoires sont affichés une seule fois.
 */
class TeamController extends Controller
{
    public const ROLES = ['mutual_admin'];

    public function __construct(private TeamService $team) {}

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $role = array_key_exists((string) $request->get('role'), TenantNav::ROLE_LABELS) ? $request->get('role') : null;
        $users = User::query()->with('roles')->when($role, fn ($q) => $q->role($role))
            ->orderByDesc('active')->orderBy('first_name')->orderBy('last_name')->get();

        $providerIds = $users->pluck('care_provider_id')->filter()->unique();
        $providerNames = $providerIds->isEmpty() ? collect() : \App\Models\Central\CareProvider::on('central')->whereIn('id', $providerIds)->pluck('name', 'id');

        return view('tenant.team.index', ['users' => $users, 'role' => $role, 'me' => $request->user(), 'providerNames' => $providerNames]);
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('tenant.team.form', ['member' => new User(), 'antennas' => $this->antennas(), 'current' => [], 'providers' => $this->providers()]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate($this->rules(true), $this->messages());
        [$user, $password] = $this->team->create($data, $data['roles'] ?? []);

        return redirect()->route('team.index')->with('team_password', [$user->name, $user->email, $password])
            ->with('success', "{$user->name} fait maintenant partie de l’équipe.");
    }

    public function edit(Request $request, int $user)
    {
        $this->authorizeAdmin($request);
        $member = User::with('roles')->findOrFail($user);

        return view('tenant.team.form', ['member' => $member, 'antennas' => $this->antennas(), 'current' => $member->getRoleNames()->all(), 'providers' => $this->providers()]);
    }

    public function update(Request $request, int $user)
    {
        $this->authorizeAdmin($request);
        $member = User::findOrFail($user);
        $data = $request->validate($this->rules(false), $this->messages());
        $this->team->update($member, $data, $data['roles'] ?? [], $request->user());

        return redirect()->route('team.index')->with('success', "Fiche de {$member->name} mise à jour.");
    }

    public function toggle(Request $request, int $user)
    {
        $this->authorizeAdmin($request);
        $member = User::findOrFail($user);
        $this->team->setActive($member, ! $member->active, $request->user());

        return back()->with('success', $member->active ? "{$member->name} peut de nouveau se connecter." : "Le compte de {$member->name} est désactivé : il ne peut plus se connecter.");
    }

    public function resetPassword(Request $request, int $user)
    {
        $this->authorizeAdmin($request);
        $member = User::findOrFail($user);
        abort_if($member->is($request->user()), 422, 'Changez votre propre mot de passe depuis « Mon mot de passe ».');
        $password = $this->team->resetPassword($member);

        return back()->with('team_password', [$member->name, $member->email, $password])
            ->with('success', "Nouveau mot de passe provisoire pour {$member->name}.");
    }

    public function resetMfa(Request $request, int $user)
    {
        $this->authorizeAdmin($request);
        $member = User::findOrFail($user);
        $this->team->resetMfa($member);

        return back()->with('success', "{$member->name} configurera de nouveau la double authentification à sa prochaine connexion.");
    }

    /** « Rôles et droits » : ce que chaque rôle voit dans le menu, et ses autorisations de validation. */
    public function roles(Request $request)
    {
        $this->authorizeAdmin($request);
        $existing = Role::where('guard_name', 'web')->pluck('name')->all();
        $counts = collect(TenantNav::ROLE_LABELS)->map(fn ($l, $r) => in_array($r, $existing, true) ? User::role($r)->where('active', true)->count() : 0);

        return view('tenant.team.roles', ['groups' => TenantNav::groups(), 'counts' => $counts]);
    }

    // ---

    private function rules(bool $creating): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name'  => ['required', 'string', 'max:120'],
            'email'      => $creating ? ['required', 'email:rfc', 'max:180'] : ['prohibited'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'antenna_id' => ['nullable', 'integer', Rule::exists('antennas', 'id')],
            'roles'      => ['required', 'array'],
            'roles.*'    => [Rule::in(config('mxconnect.mutual_roles', []))],
            'care_provider_id' => ['nullable', 'integer'],
        ];
    }

    private function messages(): array
    {
        return ['roles.required' => 'Choisissez au moins un rôle.', 'email.prohibited' => 'L’adresse e-mail d’un compte ne se modifie pas : créez un nouveau compte.'];
    }

    /** Prestataires conventionnés (pour rattacher un compte « Prestataire de soins »). */
    private function providers(): \Illuminate\Support\Collection
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('provider_conventions')) {
            return collect();
        }
        $ids = \App\Models\Tenant\ProviderConvention::pluck('care_provider_id');

        return \App\Models\Central\CareProvider::on('central')->whereIn('id', $ids)->orderBy('name')->pluck('name', 'id');
    }

    private function antennas()
    {
        return Antenna::orderBy('name')->get(['id', 'name']);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(self::ROLES), 403);
    }
}
