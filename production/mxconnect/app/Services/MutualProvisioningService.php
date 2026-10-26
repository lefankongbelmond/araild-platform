<?php

namespace App\Services;

use App\Models\Central\Mutual;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a new mutual (tenant) from the Super Admin UI:
 *   1. inserts the mutual registry row (central),
 *   2. stancl/tenancy creates its dedicated database,
 *   3. runs the tenant migrations against that database,
 *   4. seeds initial parameters (roles, SYSCOHADA chart, risk matrix).
 *
 * Nothing is hard-coded per country — country/currency/locale/providers are passed in as ids.
 */
class MutualProvisioningService
{
    /**
     * @param  array{name:string,slug:string,country_id:int,currency_id:int,locale_id:int,region?:string,city?:string,approved_by?:int}  $data
     */
    public function create(array $data): Mutual
    {
        // Enforce configurable platform cap (0 = unlimited).
        $max = config('mxconnect.max_mutuals');
        if ($max > 0 && Mutual::operational()->where('status', '!=', 'suspended')->count() >= $max) {
            throw new \RuntimeException("Platform mutual limit ({$max}) reached.");
        }

        // CORRECTION #7 : PAS de DB::transaction() ici. Mutual::create() déclenche
        // (synchrone) CREATE DATABASE + migrations : le DDL MySQL/MariaDB fait un
        // COMMIT implicite qui casse la transaction englobante. En cas d'échec du
        // provisioning, on nettoie explicitement (domaine + ligne + base).
        $id = $this->nextId();

        // Stockage des données (2026-11-02) :
        //  - VPS (mode auto) : base dédiée créée par MX-CONNECT (pipeline stancl) ;
        //  - mutualisé, base saisie : base pré-créée dans le panneau, vérifiée AVANT toute écriture ;
        //  - mutualisé, sans base saisie : tables préfixées créées AUTOMATIQUEMENT dans la base centrale.
        $dbFields = [];
        if ($this->manualMode()) {
            $dbFields = (($data['storage'] ?? null) !== 'auto' && trim((string) ($data['db_name'] ?? '')) !== '')
                ? $this->checkManualDatabase($data)
                : $this->prefixFields($id);
        }
        unset($data['db_name'], $data['db_username'], $data['db_password'], $data['storage']);

        try {
            /** @var Mutual $mutual */
            $mutual = Mutual::create(array_merge($data, $dbFields, [
                'id'     => $id,
                'status' => 'pending',
            ]));

            // Sous-domaine : <slug>.mx-connect.com (ex. together.mx-connect.com).
            // config() et non env() : env() renvoie null une fois `config:cache` fait,
            // et CENTRAL_DOMAIN=www.mx-connect.com produisait mut01.www.mx-connect.com.
            $label = Str::slug($data['slug'] ?? str_replace('_', '', $id));
            $mutual->domains()->create([
                'domain' => $label . '.' . config('mxconnect.tenant_base_domain'),
            ]);

            // Contrôle : les migrations + le seed ont-ils réellement tourné ?
            // (ex. tenants:migrate sans --force en production ne fait rien.)
            $ready = $mutual->run(fn () => \Illuminate\Support\Facades\Schema::hasTable('users')
                && \Illuminate\Support\Facades\DB::table('roles')->count() > 0);
            if (! $ready) {
                throw new \RuntimeException('La base de la mutuelle a été créée mais ses tables ou ses rôles sont absents (migrations/seed non exécutés).');
            }

            // stancl event pipeline (CreateDatabase, MigrateDatabase, SeedDatabase)
            // runs on tenant creation per config/tenancy.php. Seeding installs:
            // roles/permissions, SYSCOHADA chart of accounts, the 15-risk starter matrix.

            return $mutual;
        } catch (\Throwable $e) {
            $this->rollbackPartial($id);
            throw $e;
        }
    }

    /** Prochain identifiant mut_NN libre (robuste aux suppressions). */
    protected function nextId(): string
    {
        $n = Mutual::count() + 1;
        do {
            $id = 'mut_' . Str::padLeft((string) $n++, 2, '0');
        } while (Mutual::whereKey($id)->exists());

        return $id;
    }

    /** Hébergement mutualisé (pas de CREATE DATABASE) : modes « manual » et « prefix ». */
    protected function manualMode(): bool
    {
        return in_array(config('mxconnect.tenant_db_mode'), ['manual', 'prefix'], true);
    }

    /**
     * Tables préfixées dans la base centrale : rien à créer dans le panneau d'hébergement.
     * stancl passe tenancy_db_prefix à la connexion « tenant » (préfixe de toutes les tables).
     *
     * @return array<string,string>
     */
    protected function prefixFields(string $id): array
    {
        $prefix = TenantStorage::prefixFor($id);
        if (TenantStorage::tables($prefix)) {
            throw new \RuntimeException("Des tables « {$prefix}… » existent déjà dans la base centrale (création interrompue ?). Contactez le support avant de recommencer.");
        }

        return [
            'tenancy_db_name'   => config('database.connections.central.database'),
            'tenancy_db_prefix' => $prefix,
        ];
    }

    /**
     * Vérifie la base saisie (mode manual) et renvoie les attributs internes
     * stancl (tenancy_db_*) à enregistrer sur la mutuelle.
     *
     * @return array<string,string>
     */
    protected function checkManualDatabase(array $data): array
    {
        $name = trim((string) ($data['db_name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Nom de la base de données manquant.');
        }
        if ($name === config('database.connections.central.database')) {
            throw new \RuntimeException("La base « {$name} » est la base centrale : créez une base dédiée à la mutuelle.");
        }
        $used = Mutual::all()->contains(fn (Mutual $m) => $m->getInternal('db_name') === $name
            || $m->database()->getName() === $name);
        if ($used) {
            throw new \RuntimeException("La base « {$name} » est déjà utilisée par une autre mutuelle.");
        }

        $user = ($data['db_username'] ?? '') !== '' ? $data['db_username'] : null;
        $pass = ($data['db_password'] ?? '') !== '' ? $data['db_password'] : null;

        $conn = $this->probeConnection($name, $user, $pass);
        try {
            $tables = DB::connection($conn)->select('SHOW TABLES');
        } catch (\Throwable $e) {
            throw new \RuntimeException("Connexion impossible à la base « {$name} » : vérifiez le nom, l'utilisateur et le mot de passe "
                . "(et que l'utilisateur a bien les droits sur cette base). Détail : " . $e->getMessage());
        } finally {
            DB::purge($conn);
        }
        if (count($tables) > 0) {
            throw new \RuntimeException("La base « {$name} » n'est pas vide (" . count($tables) . ' table(s)). Utilisez une base neuve.');
        }

        return array_filter([
            'tenancy_db_name'     => $name,
            'tenancy_db_username' => $user,
            'tenancy_db_password' => $pass,
        ], fn ($v) => $v !== null);
    }

    /** Déclare une connexion temporaire vers la base de la mutuelle. */
    protected function probeConnection(string $name, ?string $user, ?string $pass): string
    {
        $base = config('database.connections.' . config('tenancy.database.template_tenant_connection', 'mysql'));
        $conn = 'mxconnect_probe';
        config(["database.connections.$conn" => array_merge($base, array_filter([
            'database' => $name,
            'username' => $user,
            'password' => $pass,
        ], fn ($v) => $v !== null))]);
        DB::purge($conn);

        return $conn;
    }

    /** Nettoyage best-effort d'un provisioning interrompu. */
    protected function rollbackPartial(string $id): void
    {
        try {
            $mutual = Mutual::find($id);

            if ($mutual && TenantStorage::isPrefixed($mutual)) {
                // Tables préfixées : on supprime seulement les tables de CETTE mutuelle.
                TenantStorage::dropTables(TenantStorage::prefix($mutual));
            } elseif ($this->manualMode()) {
                // Base pré-créée : on ne peut pas la supprimer, on la remet à vide.
                if ($mutual) {
                    $cfg = $mutual->database();
                    $conn = $this->probeConnection($cfg->getName(), $cfg->getUsername(), $cfg->getPassword());
                    $db = DB::connection($conn);
                    $db->statement('SET FOREIGN_KEY_CHECKS=0');
                    foreach ($db->select('SHOW TABLES') as $row) {
                        $db->statement('DROP TABLE IF EXISTS `' . str_replace('`', '', array_values((array) $row)[0]) . '`');
                    }
                    $db->statement('SET FOREIGN_KEY_CHECKS=1');
                    DB::purge($conn);
                }
            } else {
                $db = config('tenancy.database.prefix') . $id . config('tenancy.database.suffix');
                DB::connection('central')->statement('DROP DATABASE IF EXISTS `' . str_replace('`', '', $db) . '`');
            }

            DB::connection('central')->table('domains')->where('mutual_id', $id)->delete();
            DB::connection('central')->table('mutuals')->where('id', $id)->delete();
        } catch (\Throwable) {
            // on remonte l'erreur d'origine
        }
    }

    /**
     * Crée (ou réactive) un utilisateur du back-office DANS la base de la mutuelle.
     * Renvoie le mot de passe (généré si non fourni).
     */
    public function createUser(Mutual $mutual, string $email, string $first = 'Admin', string $last = 'Mutuelle',
                               string $role = 'mutual_admin', ?string $password = null): string
    {
        $password ??= Str::password(16, symbols: false);
        $email = strtolower(trim($email));

        $mutual->run(function () use ($email, $first, $last, $role, $password) {
            $user = \App\Models\Tenant\User::withTrashed()->firstOrNew(['email' => $email]);
            $user->fill([
                'first_name' => $first,
                'last_name'  => $last,
                'password'   => $password,
                'active'     => true,
            ]);
            $user->deleted_at = null;
            if (\App\Services\Tenant\TeamService::hasTeamColumns()) {
                $user->must_change_password = true;   // mot de passe affiché une fois : à remplacer à la 1re connexion
            }
            $user->save();
            $user->syncRoles([$role]);
        });

        return $password;
    }

    /** Approve (agrément) — flips status and stamps who/when, audited. */
    public function approve(Mutual $mutual, int $networkUserId): void
    {
        $mutual->update([
            'status'      => 'approved',
            'approved_at' => now()->toDateString(),
            'approved_by' => $networkUserId,
        ]);
        $this->log($mutual, 'approved', $networkUserId);
    }

    /**
     * Suspension (2026-11-02) : l'espace de la mutuelle est fermé (middleware
     * EnsureMutualActive) ; les données sont conservées. Le motif est obligatoire.
     */
    public function suspend(Mutual $mutual, string $reason, int $networkUserId): void
    {
        $mutual->status = 'suspended';
        $mutual->suspended_at = now()->toDateTimeString();
        $mutual->suspension_reason = $reason;
        $mutual->save();
        $this->log($mutual, 'suspended', $networkUserId, $reason);
    }

    /** Réactivation : retour à « agréée » si la mutuelle l'avait été, sinon « en attente ». */
    public function reactivate(Mutual $mutual, int $networkUserId): void
    {
        $mutual->status = $mutual->approved_at ? 'approved' : 'pending';
        $mutual->suspended_at = null;
        $mutual->suspension_reason = null;
        $mutual->save();
        $this->log($mutual, 'reactivated', $networkUserId);
    }

    /** Historique des changements d'état (dans les données de la mutuelle, 30 derniers). */
    private function log(Mutual $mutual, string $action, int $by, ?string $note = null): void
    {
        $history = (array) ($mutual->status_history ?? []);
        $history[] = ['at' => now()->toDateTimeString(), 'action' => $action, 'by' => $by, 'note' => $note];
        $mutual->status_history = array_slice($history, -30);
        $mutual->save();
    }
}
