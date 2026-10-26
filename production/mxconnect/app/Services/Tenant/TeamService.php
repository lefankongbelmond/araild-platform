<?php

namespace App\Services\Tenant;

use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Équipe d'une mutuelle (base de la mutuelle). Règles :
 *  - rôles pris dans la liste officielle (config mxconnect.mutual_roles) ;
 *  - contrôle « 4 yeux » : jamais deux rôles incompatibles sur la même personne
 *    (config mxconnect.incompatible_role_pairs) ;
 *  - il reste toujours au moins UN administrateur actif ;
 *  - mot de passe provisoire affiché une seule fois, à changer à la 1re connexion ;
 *  - aucun mot de passe n'est envoyé par e-mail.
 */
class TeamService
{
    /** @param string[] $roles */
    public static function checkRoles(array $roles): array
    {
        $roles = array_values(array_intersect(config('mxconnect.mutual_roles', []), $roles));
        if (! $roles) {
            throw ValidationException::withMessages(['roles' => 'Choisissez au moins un rôle.']);
        }
        foreach (config('mxconnect.incompatible_role_pairs', []) as [$a, $b]) {
            if (in_array($a, $roles, true) && in_array($b, $roles, true)) {
                $la = \App\Support\TenantNav::ROLE_LABELS[$a] ?? $a;
                $lb = \App\Support\TenantNav::ROLE_LABELS[$b] ?? $b;
                throw ValidationException::withMessages(['roles' => "Contrôle « 4 yeux » : une même personne ne peut pas être à la fois « {$la} » et « {$lb} »."]);
            }
        }

        return $roles;
    }

    /** @return array{0:User,1:string} l'utilisateur et son mot de passe provisoire */
    public function create(array $data, array $roles): array
    {
        $roles = self::checkRoles($roles);
        $email = mb_strtolower(trim($data['email']));
        if (User::withTrashed()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Un membre de l’équipe utilise déjà cette adresse e-mail.']);
        }
        $password = self::temporaryPassword();

        $user = DB::transaction(function () use ($data, $email, $roles, $password) {
            $user = User::create([
                'first_name' => trim($data['first_name']), 'last_name' => trim($data['last_name']), 'email' => $email,
                'phone' => $data['phone'] ?? null, 'antenna_id' => $data['antenna_id'] ?? null,
                'password' => $password, 'active' => true,
            ] + (self::hasTeamColumns() ? ['must_change_password' => true] : []));
            $user->syncRoles($roles);

            return $user;
        });

        return [$user, $password];
    }

    public function update(User $user, array $data, array $roles, User $by): User
    {
        $roles = self::checkRoles($roles);
        if ($user->hasRole('mutual_admin') && ! in_array('mutual_admin', $roles, true)) {
            $this->assertAnotherAdmin($user, 'retirer le rôle d’administrateur');
        }
        DB::transaction(function () use ($user, $data, $roles) {
            $user->update(['first_name' => trim($data['first_name']), 'last_name' => trim($data['last_name']),
                'phone' => $data['phone'] ?? null, 'antenna_id' => $data['antenna_id'] ?? null]);
            $user->syncRoles($roles);
        });

        return $user;
    }

    public function setActive(User $user, bool $active, User $by): void
    {
        if (! $active) {
            if ($user->is($by)) {
                throw ValidationException::withMessages(['team' => 'Vous ne pouvez pas désactiver votre propre compte.']);
            }
            if ($user->hasRole('mutual_admin')) {
                $this->assertAnotherAdmin($user, 'désactiver ce compte');
            }
        }
        $user->update(['active' => $active]);
    }

    /** Nouveau mot de passe provisoire (affiché une fois, à changer à la connexion). */
    public function resetPassword(User $user): string
    {
        $password = self::temporaryPassword();
        $user->forceFill(['password' => $password, 'remember_token' => null] + (self::hasTeamColumns() ? ['must_change_password' => true] : []))->save();

        return $password;
    }

    /** Double authentification à reconfigurer à la prochaine connexion (téléphone perdu, changé…). */
    public function resetMfa(User $user): void
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
    }

    public static function temporaryPassword(): string
    {
        return Str::password(14, symbols: false);
    }

    public static function hasTeamColumns(): bool
    {
        static $has = [];
        $key = DB::connection()->getDatabaseName();

        return $has[$key] ??= Schema::hasColumn('users', 'must_change_password');
    }

    private function assertAnotherAdmin(User $user, string $action): void
    {
        $others = User::role('mutual_admin')->where('active', true)->whereKeyNot($user->id)->count();
        if ($others === 0) {
            throw ValidationException::withMessages(['roles' => "Impossible de {$action} : c’est le dernier administrateur actif de la mutuelle. Nommez d’abord un autre administrateur."]);
        }
    }
}
