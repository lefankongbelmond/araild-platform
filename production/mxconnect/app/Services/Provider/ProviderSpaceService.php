<?php

namespace App\Services\Provider;

use App\Models\Central\CareProvider;
use App\Models\Central\Domain;
use App\Models\Central\Mutual;
use App\Models\Provider\ProviderAccount;
use App\Models\Provider\ProviderAffiliation;
use App\Models\Provider\ProviderHandoff;
use App\Models\Provider\ProviderMembership;
use App\Models\Tenant\ProviderConvention;
use App\Models\Tenant\User;
use App\Support\ProviderAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Espace prestataire central (Portail prestataire V2, phase 2, 2026-11-16).
 *
 *  - Les établissements et leurs personnels sont CENTRAUX ; les soins, factures et adhérents
 *    restent dans la base de chaque mutuelle. Une action « dans » une mutuelle se fait sur
 *    son adresse (sous-domaine), après une entrée par jeton à usage unique (60 s).
 *  - À la première entrée, la mutuelle reçoit un compte « Prestataire de soins » au nom de la
 *    personne (users.provider_account_id) : ses saisies restent attribuées à une personne
 *    réelle, le contrôle « 4 yeux » et le journal de la mutuelle continuent de fonctionner, et
 *    la mutuelle peut couper cet accès (compte désactivé) sans dépendre de l'établissement.
 *  - Jamais de requête d'une mutuelle sur les tables d'une autre : chaque lecture se fait dans
 *    $mutual->run(), une mutuelle à la fois.
 */
class ProviderSpaceService
{
    public const HANDOFF_SECONDS = 60;

    /** Destination dans le portail de la mutuelle => [route, droit nécessaire]. */
    public const TARGETS = [
        'verify'   => ['portal.index', 'rights.verify'],
        'claims'   => ['portal.claims', 'claims.view'],
        'invoices' => ['portal.invoices', 'invoices.view'],
    ];

    /** Droit de l'établissement exigé par chaque route du portail de la mutuelle. */
    public const PORTAL_ROUTE_PERMS = [
        'portal.index'                  => 'rights.verify',
        'portal.verify'                 => 'rights.verify',
        'portal.declare'                => 'care.declare',
        'portal.declare.store'          => 'care.declare',
        'portal.claims'                 => 'claims.view',
        'portal.invoices'               => 'invoices.view',
        'portal.invoices.store'         => 'invoices.bill',
        'portal.invoices.document.store'=> 'invoices.bill',
        'portal.invoices.document'      => 'invoices.view',
    ];

    // --- Mutuelles de l'établissement ---

    /** Liens établissement ↔ mutuelle (toutes situations), mutuelles réelles seulement. */
    public function affiliations(CareProvider $provider): Collection
    {
        return ProviderAffiliation::with('mutual')->where('care_provider_id', $provider->id)
            ->whereIn('mutual_id', Mutual::query()->operational()->select('id'))
            ->orderByRaw("FIELD(status, 'approved', 'requested', 'suspended', 'rejected', 'withdrawn')")->get();
    }

    /** Mutuelles où une convention existe (en place ou suspendue) et dont l'espace est ouvert. */
    public function conventionedMutuals(CareProvider $provider): Collection
    {
        return $this->affiliations($provider)->whereIn('status', ['approved', 'suspended'])
            ->filter(fn ($a) => $a->mutual && $a->mutual->status !== 'suspended')
            ->map(fn ($a) => $a->mutual)->values();
    }

    /** Mutuelles auxquelles l'établissement peut demander une affiliation (même pays). */
    public function requestableMutuals(CareProvider $provider): Collection
    {
        $taken = ProviderAffiliation::where('care_provider_id', $provider->id)->whereIn('status', ['requested', 'approved', 'suspended'])->pluck('mutual_id');

        return Mutual::query()->operational()->where('status', '!=', 'suspended')
            ->when($provider->country_id, fn ($q) => $q->where('country_id', $provider->country_id))
            ->whereNotIn('id', $taken)->orderBy('name')->get();
    }

    public function mutualBaseUrl(Mutual $mutual): ?string
    {
        $domain = Domain::where('mutual_id', $mutual->id)->value('domain');
        if (! $domain) {
            return null;
        }
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);

        return "{$scheme}://{$domain}" . ($port ? ":{$port}" : '');
    }

    // --- Entrée dans le portail d'une mutuelle ---

    /** Crée un jeton d'entrée et renvoie l'adresse à ouvrir ; null si impossible (message dans $why). */
    public function handoffUrl(ProviderAccount $account, ProviderMembership $membership, Mutual $mutual, string $target, Request $request, ?string &$why = null): ?string
    {
        [, $perm] = self::TARGETS[$target] ?? self::TARGETS['verify'];
        if (! $membership->can($perm)) {
            $why = 'Votre rôle dans l’établissement ne permet pas cette action.';

            return null;
        }
        $aff = ProviderAffiliation::where('care_provider_id', $membership->care_provider_id)->where('mutual_id', $mutual->id)
            ->whereIn('status', ['approved', 'suspended'])->first();
        $base = $this->mutualBaseUrl($mutual);
        if (! $aff || ! $base || $mutual->status === 'suspended') {
            $why = 'Aucune convention en place avec cette mutuelle.';

            return null;
        }

        ProviderHandoff::where('expires_at', '<', now()->subDay())->delete();   // ménage
        $token = Str::random(64);
        ProviderHandoff::create([
            'provider_account_id' => $account->id, 'care_provider_id' => $membership->care_provider_id, 'mutual_id' => $mutual->id,
            'token_hash' => hash('sha256', $token), 'target' => $target, 'expires_at' => now()->addSeconds(self::HANDOFF_SECONDS),
            'ip' => $request->ip(), 'created_at' => now(),
        ]);

        return $base . '/prestataire/entree/' . $token;
    }

    /**
     * Côté MUTUELLE (contexte tenant initialisé) : consomme le jeton, ouvre la session du
     * compte « Prestataire de soins » de la personne. Renvoie la route de destination.
     *
     * @throws ProviderSpaceRefusal
     */
    public function enter(string $token, Request $request): string
    {
        $hash = hash('sha256', $token);
        $used = ProviderHandoff::where('token_hash', $hash)->where('mutual_id', (string) tenant('id'))
            ->whereNull('used_at')->where('expires_at', '>', now())->update(['used_at' => now()]);
        if ($used !== 1) {
            throw new ProviderSpaceRefusal('Ce lien d’entrée a expiré ou a déjà servi. Repassez par votre espace prestataire.');
        }
        $h = ProviderHandoff::where('token_hash', $hash)->firstOrFail();
        $account = ProviderAccount::find($h->provider_account_id);
        $membership = ProviderMembership::where('provider_account_id', $h->provider_account_id)->where('care_provider_id', $h->care_provider_id)->first();
        $provider = CareProvider::on('central')->find($h->care_provider_id);
        [$route, $perm] = self::TARGETS[$h->target] ?? self::TARGETS['verify'];

        if (! $account?->active || ! $membership || ! $membership->can($perm) || ! $provider || $provider->active === false) {
            throw new ProviderSpaceRefusal('Votre accès à cet établissement n’est plus actif. Contactez l’administrateur de votre établissement.');
        }
        if (! ProviderConvention::forProvider($provider->id)) {
            throw new ProviderSpaceRefusal('Aucune convention de votre établissement avec cette mutuelle.');
        }

        $user = $this->tenantUser($account, $provider);
        if (! $user->active) {
            throw new ProviderSpaceRefusal('La mutuelle a désactivé votre accès à son portail. Contactez la mutuelle.');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        // Double authentification déjà passée dans l'espace prestataire (obligatoire là-bas).
        $request->session()->put('mfa_passed', true);
        $request->session()->put('provider_space', ['account' => $account->id, 'membership' => $membership->id]);
        if (\App\Services\Tenant\TeamService::hasTeamColumns()) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
        }

        return $route;
    }

    /** Compte de la mutuelle au nom de la personne (créé à la 1re entrée, noms tenus à jour). */
    public function tenantUser(ProviderAccount $account, CareProvider $provider): User
    {
        $user = User::where('provider_account_id', $account->id)->where('care_provider_id', $provider->id)->first();
        if ($user) {
            if ($user->first_name !== $account->first_name || $user->last_name !== $account->last_name) {
                $user->forceFill(['first_name' => $account->first_name, 'last_name' => $account->last_name])->save();
            }

            return $user;
        }

        return DB::transaction(function () use ($account, $provider) {
            $user = User::create([
                'first_name' => $account->first_name, 'last_name' => $account->last_name,
                // Adresse technique (jamais de courrier) : la vraie adresse reste dans l'espace central.
                'email' => "espace-prestataire-{$account->id}-{$provider->id}@prestataires.mx-connect.invalid",
                'phone' => $account->phone ? Str::limit($account->phone, 20, '') : null,
                // Aucun mot de passe utilisable : l'entrée se fait seulement par l'espace prestataire.
                'password' => Str::random(48), 'active' => true, 'care_provider_id' => $provider->id,
                'provider_account_id' => $account->id, 'two_factor_confirmed_at' => now(),
            ] + (\App\Services\Tenant\TeamService::hasTeamColumns() ? ['must_change_password' => false] : []));
            $user->syncRoles(['provider']);

            return $user;
        });
    }

    /**
     * À chaque requête du portail d'une mutuelle faite par un compte rattaché : la personne et
     * son rattachement sont-ils toujours actifs ? Renvoie les droits effectifs, ou null.
     */
    public function linkedPermissions(User $user): ?array
    {
        if (! $user->provider_account_id) {
            return null;
        }
        $account = ProviderAccount::find($user->provider_account_id);
        $m = ProviderMembership::where('provider_account_id', $user->provider_account_id)->where('care_provider_id', $user->care_provider_id)->first();
        if (! $account?->active || ! $m?->active) {
            return [];
        }

        return $m->effectivePermissions();
    }

    // --- Conventions (appelé côté mutuelle) ---

    /** Tient à jour l'index central après création / modification / suspension d'une convention. */
    public static function syncFromConvention(ProviderConvention $c, string $event, ?string $who = null): void
    {
        $mutualId = (string) tenant('id');
        if (! $mutualId || ! \Illuminate\Support\Facades\Schema::connection('central')->hasTable('provider_affiliations')) {
            return;
        }
        $aff = ProviderAffiliation::firstOrNew(['care_provider_id' => $c->care_provider_id, 'mutual_id' => $mutualId]);
        $wasApproved = $aff->exists && $aff->status === 'approved';
        $aff->status = $c->active ? 'approved' : 'suspended';
        $aff->decided_at = $wasApproved && $c->active ? $aff->decided_at : now();
        $aff->log($event, $who);
        $aff->save();

        // Un établissement inscrit en ligne devient « connu » dès sa première convention.
        CareProvider::on('central')->whereKey($c->care_provider_id)->where('registration_status', 'pending')->update(['registration_status' => 'approved']);
    }

    // --- Inscription d'un établissement ---

    /** @return array{0:ProviderAccount,1:CareProvider} */
    public function register(array $d): array
    {
        return DB::connection('central')->transaction(function () use ($d) {
            $provider = CareProvider::on('central')->create([
                'name' => trim($d['establishment']), 'type' => $d['type'], 'country_id' => $d['country_id'] ?? null,
                'locality' => $d['locality'] ?? null, 'phone' => $d['establishment_phone'] ?? null, 'email' => $d['establishment_email'] ?? null,
                'approval_number' => $d['approval_number'] ?? null, 'active' => true, 'registration_status' => 'pending',
            ]);
            $account = ProviderAccount::create([
                'first_name' => trim($d['first_name']), 'last_name' => trim($d['last_name']), 'email' => Str::lower(trim($d['email'])),
                'phone' => $d['phone'] ?? null, 'password' => $d['password'], 'active' => true,
            ]);
            ProviderMembership::create(['provider_account_id' => $account->id, 'care_provider_id' => $provider->id, 'role' => 'admin', 'active' => true]);
            foreach (array_unique((array) ($d['mutuals'] ?? [])) as $mutualId) {
                $this->requestAffiliation($provider, (string) $mutualId, $account, $d['message'] ?? null);
            }

            return [$account, $provider];
        });
    }

    public function requestAffiliation(CareProvider $provider, string $mutualId, ProviderAccount $by, ?string $message): ?ProviderAffiliation
    {
        if (! $this->requestableMutuals($provider)->contains('id', $mutualId)) {
            return null;
        }
        $aff = ProviderAffiliation::firstOrNew(['care_provider_id' => $provider->id, 'mutual_id' => $mutualId]);
        $aff->fill(['status' => 'requested', 'message' => $message ? Str::limit(trim($message), 1000, '') : null,
            'requested_by' => $by->id, 'decision_note' => null, 'decided_at' => null]);
        $aff->log('Demande d’affiliation envoyée', $by->name);
        $aff->save();

        return $aff;
    }

    /** Droits utiles à l'écran (rôle, libellés) — aide aux vues. */
    public static function roleOptions(): array
    {
        return ProviderAccess::ROLES;
    }
}
