<?php

namespace App\Services;

use App\Models\Central\MemberActivationCode;
use App\Models\Central\MemberLink;
use App\Models\Central\Mutual;
use App\Models\Social\AdhesionRequest;
use App\Services\Tenant\ExampleFormulaService;
use App\Support\TestEnvironment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * RÉINITIALISATION DE LA MUTUELLE TEST (mission « Mutuelle paramétrable », L6, 2026-11-22).
 *
 * Efface les données d'ACTIVITÉ d'une mutuelle marquée « test » (adhérents, cotisations, soins,
 * trésorerie, comptabilité…) et garde sa CONFIGURATION et ses TRACES (équipe, rôles, plan comptable,
 * listes de référence, conventions, réglages, journaux d'audit). Garde-fous :
 *  - refus si la mutuelle n'est pas marquée « test » (vérifié ici, côté serveur) ;
 *  - CHAQUE table de la mutuelle est classée (KEEP / FORMULAS / RESET) : une table inconnue bloque la
 *    réinitialisation (un test vérifie que toutes les tables des migrations sont classées) ;
 *  - sauvegarde SQL complète de la mutuelle AVANT tout effacement ; échec = rien n'est effacé ;
 *  - effacement en une transaction, un seul à la fois (verrou), trace dans l'historique et l'audit.
 * Aucune autre mutuelle n'est touchée : tout se passe dans $mutual->run() (base ou préfixe propre).
 */
class TestDataResetService
{
    /** Conservées : personnes, rôles, configuration, listes de référence, traces. */
    public const KEEP = [
        'migrations', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions',
        'password_reset_tokens', 'personal_access_tokens', 'accounts', 'act_types', 'medicines', 'service_categories',
        'antennas', 'provider_conventions', 'mutual_settings', 'audits', 'audit_logs',
    ];

    /** Formules et garanties : conservées sauf si l'option « formules » est cochée. */
    public const FORMULAS = ['guarantees', 'guarantee_versions', 'coverage_rules', 'coverage_limits', 'waiting_periods', 'ceilings'];

    /** Effacées : toute l'activité (données d'essai). */
    public const RESET = [
        'members', 'dependents', 'subscriptions', 'formula_migrations', 'amendments', 'suspensions', 'radiations', 'departures', 'member_groups',
        'contribution_schedules', 'contribution_payments', 'collection_cancellations',
        'care_claims', 'prestations', 'prescribed_medicines', 'reimbursements', 'provider_invoices', 'provider_invoice_lines', 'invoice_events', 'coverage_exceptions',
        'preauthorizations', 'prescriptions', 'dispensations', 'dispensation_lines', 'rights_checks', 'beneficiary_searches', 'alerts',
        'treasury_movements', 'accounting_entries', 'accounting_days', 'accounting_periods', 'fiscal_years', 'budgets', 'budget_lines',
        'complaints', 'complaint_messages', 'member_change_requests', 'member_notifications', 'member_notification_recipients',
        'documents', 'incidents', 'risks', 'feed_posts', 'feed_comments', 'feed_post_likes',
    ];

    /** Libellés lisibles des tables effacées (écran de confirmation). */
    public const LABELS = [
        'members' => 'Adhérents', 'dependents' => 'Ayants droit', 'subscriptions' => 'Souscriptions',
        'contribution_schedules' => 'Échéances de cotisation', 'contribution_payments' => 'Paiements de cotisation',
        'care_claims' => 'Recours', 'prestations' => 'Prestations', 'provider_invoices' => 'Factures des prestataires', 'provider_invoice_lines' => 'Lignes des factures des prestataires', 'invoice_events' => 'Historique des factures des prestataires',
        'preauthorizations' => 'Accords préalables', 'prescriptions' => 'Ordonnances', 'dispensations' => 'Délivrances en pharmacie',
        'coverage_exceptions' => 'Dérogations', 'rights_checks' => 'Vérifications de droits', 'alerts' => 'Alertes',
        'treasury_movements' => 'Mouvements de trésorerie', 'accounting_entries' => 'Écritures comptables', 'fiscal_years' => 'Exercices',
        'budgets' => 'Budgets', 'complaints' => 'Réclamations', 'member_change_requests' => 'Demandes des adhérents',
        'member_notifications' => 'Notifications aux membres', 'incidents' => 'Incidents', 'risks' => 'Risques',
        'guarantees' => 'Formules (garanties)', 'guarantee_versions' => 'Versions de formules',
        'formula_migrations' => 'Changements de formule', 'amendments' => 'Avenants', 'suspensions' => 'Suspensions', 'radiations' => 'Radiations', 'departures' => 'Départs',
        'member_groups' => 'Groupes de membres', 'collection_cancellations' => 'Annulations d’encaissement',
        'prescribed_medicines' => 'Médicaments prescrits (recours)', 'reimbursements' => 'Remboursements', 'dispensation_lines' => 'Lignes de délivrance',
        'beneficiary_searches' => 'Recherches de bénéficiaires', 'accounting_days' => 'Journées comptables', 'accounting_periods' => 'Périodes comptables',
        'budget_lines' => 'Lignes de budget', 'complaint_messages' => 'Messages des réclamations',
        'member_notification_recipients' => 'Destinataires des notifications', 'documents' => 'Documents',
        'feed_posts' => 'Publications internes', 'feed_comments' => 'Commentaires internes', 'feed_post_likes' => 'Mentions « j’aime »',
        'coverage_rules' => 'Garanties des formules', 'coverage_limits' => 'Plafonds', 'waiting_periods' => 'Délais de carence', 'ceilings' => 'Plafonds (formules classiques)',
    ];

    public const BACKUP_DIR = 'app/test-resets';

    public function __construct(private BackupService $backup) {}

    /**
     * Ce que la réinitialisation effacerait : [table => lignes] pour l'activité et les formules,
     * et les tables non classées (qui bloquent).
     */
    public function inventory(Mutual $mutual): array
    {
        return $mutual->run(function () {
            $tables = $this->tables();
            $count = fn (array $list) => collect($list)->filter(fn ($t) => in_array($t, $tables, true))
                ->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();

            return [
                'reset' => $count(self::RESET),
                'formulas' => $count(self::FORMULAS),
                'unclassified' => $this->unclassified($tables),
            ];
        }) + ['links' => MemberLink::where('mutual_id', $mutual->id)->count()];
    }

    /**
     * @param  array{formulas?:bool, examples?:bool}  $opts
     * @return array{deleted:int, tables:array<string,int>, backup:string, examples:int}
     */
    public function reset(Mutual $mutual, string $confirm, string $note, array $opts, ?int $networkUserId, string $byLabel): array
    {
        if (! TestEnvironment::of($mutual)) {
            throw new AccountingRefusal('Réinitialisation refusée : cette mutuelle n’est pas marquée « MUTUELLE TEST ».');
        }
        if (trim($confirm) !== (string) $mutual->slug) {
            throw new AccountingRefusal('Confirmation incorrecte : saisissez exactement l’identifiant « ' . $mutual->slug . ' ».');
        }
        $note = trim($note);
        if (mb_strlen($note) < 5) {
            throw new AccountingRefusal('Indiquez le motif de la réinitialisation (5 caractères au moins).');
        }
        $lock = Cache::lock('test-reset:' . $mutual->id, 600);
        if (! $lock->get()) {
            throw new AccountingRefusal('Une réinitialisation de cette mutuelle est déjà en cours.');
        }

        try {
            $unclassified = $mutual->run(fn () => $this->unclassified($this->tables()));
            if ($unclassified) {
                throw new AccountingRefusal('Réinitialisation refusée : table(s) non classée(s) ' . implode(', ', $unclassified) . '. Rien n’a été effacé.');
            }

            // 1. Sauvegarde complète AVANT tout effacement.
            $file = self::backupDir() . '/' . $mutual->slug . '-' . now()->format('Ymd_His') . '.sql';
            if (! $this->backup->dumpMutual($mutual, $file)) {
                @unlink($file);
                throw new AccountingRefusal('La sauvegarde préalable a échoué : rien n’a été effacé.');
            }
            $this->prune($mutual->slug, 5);

            // 2. Effacement dans la mutuelle (une transaction ; clés étrangères suspendues le temps de vider).
            $targets = array_merge(self::RESET, ! empty($opts['formulas']) ? self::FORMULAS : []);
            $done = $mutual->run(function () use ($targets, $mutual) {
                $tables = $this->tables();
                $done = [];
                Schema::disableForeignKeyConstraints();
                try {
                    DB::transaction(function () use ($targets, $tables, &$done) {
                        foreach ($targets as $t) {
                            if (in_array($t, $tables, true)) {
                                $done[$t] = DB::table($t)->delete();
                            }
                        }
                    });
                } finally {
                    Schema::enableForeignKeyConstraints();
                }
                $this->purgeFiles($mutual);

                return $done;
            });

            // 3. Liens du réseau vers des fiches qui n'existent plus.
            $links = MemberLink::where('mutual_id', $mutual->id)->delete();
            MemberActivationCode::where('mutual_id', $mutual->id)->delete();
            if (Schema::connection('central')->hasTable('adhesion_requests')) {
                AdhesionRequest::where('mutual_id', $mutual->id)->whereNotNull('member_id')->update(['member_id' => null, 'member_code' => null]);
            }

            // 4. Formules d'exemple rechargées (seulement si les formules ont été effacées).
            $examples = 0;
            if (! empty($opts['formulas']) && ! empty($opts['examples'])) {
                $examples = $mutual->run(fn () => app(ExampleFormulaService::class)->load());
            }

            // 5. Traces : historique de la mutuelle (réseau) et journal d'audit de la mutuelle.
            $deleted = array_sum($done);
            $summary = "{$deleted} ligne(s) effacée(s)" . ($links ? ", {$links} compte(s) adhérent délié(s)" : '')
                . (! empty($opts['formulas']) ? ', formules effacées' : ', formules conservées') . ($examples ? ", {$examples} formule(s) d’exemple rechargée(s)" : '')
                . ' · sauvegarde ' . basename($file) . ' · par ' . $byLabel;
            $history = (array) ($mutual->status_history ?? []);
            $history[] = ['at' => now()->toDateTimeString(), 'action' => 'test_reset', 'by' => $networkUserId, 'note' => $note . ' — ' . $summary];
            $mutual->status_history = array_slice($history, -30);
            $mutual->save();
            $mutual->run(function () use ($done, $note, $byLabel, $file, $opts) {
                if (Schema::hasTable('audit_logs')) {
                    DB::table('audit_logs')->insert(['user_id' => auth('web')->id(), 'action' => 'test_data_reset', 'object_type' => 'mutual',
                        'after' => json_encode(['note' => $note, 'by' => $byLabel, 'backup' => basename($file), 'formulas' => ! empty($opts['formulas']), 'tables' => $done], JSON_UNESCAPED_UNICODE),
                        'ip' => request()?->ip(), 'occurred_at' => now()]);
                }
            });

            return ['deleted' => $deleted, 'tables' => $done, 'backup' => basename($file), 'examples' => $examples, 'links' => $links];
        } finally {
            $lock->release();
        }
    }

    /**
     * Dossier des sauvegardes, TOUJOURS dans le stockage de la plateforme : dans une requête de la
     * mutuelle, storage_path() pointe vers le stockage propre à la mutuelle (suffixe « tenant<id> »).
     */
    public static function backupDir(): string
    {
        $base = storage_path();
        if (function_exists('tenant') && tenant()) {
            $suffix = DIRECTORY_SEPARATOR . config('tenancy.filesystem.suffix_base', 'tenant') . tenant()->getTenantKey();
            if (str_ends_with($base, $suffix)) {
                $base = substr($base, 0, -strlen($suffix));
            }
        }

        return $base . '/' . self::BACKUP_DIR;
    }

    /** Tables réelles de la mutuelle en cours (sans le préfixe). */
    private function tables(): array
    {
        $db = DB::connection();
        $prefix = $db->getTablePrefix();
        $out = [];
        foreach ($db->select('SHOW FULL TABLES') as $row) {
            $row = array_values((array) $row);
            if (($row[1] ?? 'BASE TABLE') !== 'BASE TABLE' || ($prefix !== '' && ! str_starts_with($row[0], $prefix))) {
                continue;
            }
            $out[] = substr($row[0], strlen($prefix));
        }

        return $out;
    }

    private function unclassified(array $tables): array
    {
        return array_values(array_diff($tables, self::KEEP, self::FORMULAS, self::RESET));
    }

    /** Pièces PDF des factures de prestataires : seulement dans le stockage PROPRE à la mutuelle. */
    private function purgeFiles(Mutual $mutual): void
    {
        $disk = Storage::disk('local');
        $root = str_replace('\\', '/', $disk->path('')) . '/';
        if (str_contains($root, '/' . config('tenancy.filesystem.suffix_base', 'tenant') . $mutual->getTenantKey() . '/')) {
            $disk->deleteDirectory('provider-invoices');
        }
    }

    private function prune(string $slug, int $keep): void
    {
        $files = glob(self::backupDir() . '/' . $slug . '-*.sql') ?: [];
        sort($files);
        foreach (array_slice($files, 0, max(0, count($files) - $keep)) as $old) {
            File::delete($old);
        }
    }
}
