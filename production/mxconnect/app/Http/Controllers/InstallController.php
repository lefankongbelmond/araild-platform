<?php

namespace App\Http\Controllers;

use App\Models\Central\NetworkUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Installation / mise à jour SANS terminal (hébergement mutualisé LWS).
 *
 *   https://www.mx-connect.com/_installation?token=<INSTALL_TOKEN>
 *
 * - Désactivée tant que INSTALL_TOKEN (≥ 24 caractères) n'est pas défini dans .env.
 * - Hors groupe "web" : fonctionne avant que la clé, les sessions ou les tables
 *   n'existent. L'accès est protégé par le jeton (comparaison à temps constant).
 * - Une fois l'installation faite, VIDER INSTALL_TOKEN dans .env.
 */
class InstallController extends Controller
{
    public function show(Request $request)
    {
        $this->guard($request);

        return response()->view('install', [
            'token'  => $request->input('token'),
            'checks' => $this->checks(),
            'log'    => null,
            'state'  => $this->state(),
        ])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }

    public function run(Request $request)
    {
        $this->guard($request);
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        // Fichiers fraîchement envoyés par FTP : vider l'OPcache pour que les
        // caches Laravel soient reconstruits à partir des NOUVEAUX fichiers.
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        $action = $request->input('action');
        $log = [];
        $step = function (string $label, string $command, array $args = []) use (&$log) {
            try {
                $code = Artisan::call($command, $args);
                $log[] = [$label, $code === 0, trim(Artisan::output()) ?: 'OK'];
            } catch (\Throwable $e) {
                $log[] = [$label, false, $e->getMessage()];
            }
        };

        switch ($action) {
            case 'database':
                $log = array_merge($log, $this->saveDatabase($request));
                break;

            case 'finish':
                $this->writeEnv(['INSTALL_TOKEN' => '']);
                @unlink(base_path('bootstrap/cache/config.php'));
                try { Artisan::call('config:cache'); } catch (\Throwable) { /* l'appli fonctionne aussi sans cache */ }
                return response('<!doctype html><meta charset="utf-8"><title>MX-CONNECT</title>'
                    . '<body style="font:16px system-ui;padding:40px;max-width:640px;margin:auto">'
                    . '<h2 style="color:#0B2559">Installateur désactivé ✔</h2>'
                    . '<p>Le jeton a été effacé du fichier .env : cette page renvoie désormais une erreur 404.</p>'
                    . '<p><a href="/reseau/connexion">Aller à la connexion du réseau →</a></p></body>')
                    ->header('Cache-Control', 'no-store');

            case 'key':
                $step('Clé de chiffrement (APP_KEY)', 'key:generate', ['--force' => true]);
                $step('Vidage du cache de configuration', 'config:clear');
                break;

            case 'install':
                $v = \Illuminate\Support\Facades\Validator::make($request->all(), [
                    'admin_email'    => ['required', 'email'],
                    'admin_password' => ['required', 'string', 'min:12'],
                ]);
                if ($v->fails()) {
                    $log[] = ['Formulaire', false, implode("\n", $v->errors()->all())];
                    break;
                }
                $step('Vidage des caches', 'optimize:clear');
                $step('Migrations (base centrale)', 'migrate', ['--force' => true]);
                $step('Données de référence + rôles réseau', 'db:seed', ['--force' => true]);
                try {
                    $admin = NetworkUser::where('email', 'admin@mx-connect.com')->first()
                        ?? NetworkUser::orderBy('id')->first()
                        ?? NetworkUser::role('super_admin', 'network')->first();
                    $admin->forceFill([
                        'email'    => strtolower($request->input('admin_email')),
                        'password' => Hash::make($request->input('admin_password')),
                        'active'   => true,
                    ])->save();
                    $log[] = ['Compte super-admin', true, 'Identifiant : ' . $admin->email];
                } catch (\Throwable $e) {
                    $log[] = ['Compte super-admin', false, $e->getMessage()];
                }
                $log[] = $this->storageLink();
                $step('Conversion des images en WebP', 'mxconnect:media-webp', ['--seconds' => 25]);
                $step('Caches de production', 'optimize');
                break;

            case 'update':
                $step('Mode maintenance', 'down', ['--retry' => 30]);
                $step('Vidage des caches', 'optimize:clear');
                $step('Migrations (base centrale)', 'migrate', ['--force' => true]);
                // Uniquement les mutuelles réelles : les organisations de démonstration
                // du réseau social n'ont pas de base de données.
                $ids = \App\Models\Central\Mutual::operational()->pluck('id')->all();
                if ($ids) {
                    $step('Migrations (bases des mutuelles)', 'tenants:migrate', ['--force' => true, '--tenants' => $ids]);
                } else {
                    $log[] = ['Migrations (bases des mutuelles)', true, 'Aucune mutuelle à mettre à jour.'];
                }
                $log[] = $this->storageLink();
                $step('Caches de production', 'optimize');
                $step('Redémarrage des files', 'queue:restart');
                $step('Remise en ligne', 'up');
                break;

            case 'clear':
                $step('Vidage de tous les caches', 'optimize:clear');
                break;

            case 'site':
                $log = array_merge($log, $this->saveSite($request));
                break;

            case 'mail':
                $log = array_merge($log, $this->saveMail($request));
                break;

            case 'mfa_reset':
                $email = trim((string) $request->input('admin_email'));
                $user = $email !== '' ? \App\Models\Central\NetworkUser::where('email', $email)->first() : null;
                if (! $user) {
                    $log[] = ['Double authentification', false, 'Aucun administrateur réseau avec cette adresse.'];
                } else {
                    $user->forceFill(['mfa_secret' => null, 'mfa_enabled' => false])->save();
                    $log[] = ['Double authentification', true, "Réinitialisée pour {$email}. À la prochaine connexion, un nouveau QR code sera proposé."];
                }
                break;

            case 'logs':
                $log = array_merge($log, $this->recentErrors());
                break;

            case 'demo':
                $step('Données de démonstration (réseau social)', 'mxconnect:demo-social');
                break;

            case 'demo_remove':
                $step('Suppression des données de démonstration', 'mxconnect:demo-social', ['--remove' => true]);
                break;

            case 'health':
                $step('Santé des bases mutuelles', 'mxconnect:tenants-health');
                break;

            default:
                abort(422, 'Action inconnue.');
        }

        return response()->view('install', [
            'token'  => $request->input('token'),
            'checks' => $this->checks(),
            'log'    => $log,
            'state'  => $this->state(),
        ])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }

    /** Teste puis enregistre les accès MySQL dans .env (aucune édition manuelle). */
    protected function saveDatabase(Request $request): array
    {
        $in = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $request->only([
            'db_host', 'db_port', 'db_database', 'db_username', 'db_password', 'app_url',
        ]));
        if (empty($in['db_host']) || empty($in['db_database']) || empty($in['db_username'])) {
            return [['Base de données', false, 'Hôte, nom de la base et utilisateur sont obligatoires.']];
        }
        $port = (int) ($in['db_port'] ?: 3306);
        // Si le mot de passe est laissé vide, on garde celui déjà enregistré.
        $password = ($in['db_password'] ?? '') !== '' ? $in['db_password'] : (string) config('database.connections.central.password');

        try {
            $pdo = new \PDO("mysql:host={$in['db_host']};port={$port};dbname={$in['db_database']};charset=utf8mb4",
                $in['db_username'], $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 8]);
            $version = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable $e) {
            return [['Connexion à la base', false, "Échec : {$e->getMessage()}\nVérifiez l'hôte, le nom complet de la base (avec le préfixe LWS), l'utilisateur et le mot de passe."]];
        }

        $pairs = [
            'DB_HOST'     => $in['db_host'],
            'DB_PORT'     => (string) $port,
            'DB_DATABASE' => $in['db_database'],
            'DB_USERNAME' => $in['db_username'],
            'DB_PASSWORD' => $password,
        ];
        if (! empty($in['app_url'])) {
            $pairs['APP_URL'] = rtrim($in['app_url'], '/');
        }
        $this->writeEnv($pairs);
        @unlink(base_path('bootstrap/cache/config.php'));

        return [['Connexion à la base', true, "OK — MySQL/MariaDB {$version}. Accès enregistrés dans .env. Rechargez la page pour continuer."]];
    }

    /** Adresse publique du site (APP_URL) et domaine central, sans éditer .env à la main. */
    protected function saveSite(Request $request): array
    {
        $url = rtrim(trim((string) $request->input('app_url')), '/');
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return [['Adresse du site', false, 'Adresse invalide. Exemple : https://m.araild.com']];
        }
        $bare = preg_replace('/^www\./', '', $host);
        $pairs = [
            'APP_URL'          => $url,
            'CENTRAL_DOMAIN'   => $host,
            'CENTRAL_DOMAINS'  => implode(',', array_unique([$host, $bare, 'www.' . $bare])),
        ];
        $contact = trim((string) $request->input('contact_email'));
        if ($contact !== '') {
            if (! filter_var($contact, FILTER_VALIDATE_EMAIL)) {
                return [['Adresse du site', false, 'E-mail de contact invalide.']];
            }
            $pairs['MXCONNECT_CONTACT_EMAIL'] = $contact;
        }
        $this->writeEnv($pairs);
        @unlink(base_path('bootstrap/cache/config.php'));
        @unlink(base_path('bootstrap/cache/routes-v7.php'));

        return [['Adresse du site', true, "Enregistrée : {$url}. Cliquez ensuite sur « Appliquer la mise à jour »."]];
    }

    /** Envoi d'e-mails (SMTP LWS ou autre) : test réel d'envoi avant enregistrement. */
    protected function saveMail(Request $request): array
    {
        $in = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $request->only([
            'mail_host', 'mail_port', 'mail_security', 'mail_username', 'mail_password', 'mail_from', 'mail_from_name', 'mail_test_to',
        ]));
        if (empty($in['mail_host']) || empty($in['mail_from']) || ! filter_var($in['mail_from'], FILTER_VALIDATE_EMAIL)) {
            return [['E-mails', false, 'Serveur SMTP et adresse d’expédition valides obligatoires.']];
        }
        $port = (int) ($in['mail_port'] ?: 465);
        $scheme = ($in['mail_security'] ?? 'ssl') === 'ssl' ? 'smtps' : 'smtp';   // 465 = SSL ; 587 = STARTTLS
        $password = ($in['mail_password'] ?? '') !== '' ? $in['mail_password'] : (string) config('mail.mailers.smtp.password');
        $fromName = ($in['mail_from_name'] ?? '') ?: 'MX-CONNECT';

        // Test réel avec ces paramètres, avant d'écrire quoi que ce soit.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => $scheme, 'mail.mailers.smtp.host' => $in['mail_host'], 'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => ($in['mail_username'] ?? '') ?: null, 'mail.mailers.smtp.password' => $password,
            'mail.mailers.smtp.timeout' => 15,
            'mail.from.address' => $in['mail_from'], 'mail.from.name' => $fromName,
        ]);
        app('mail.manager')->forgetMailers();
        $to = filter_var($in['mail_test_to'] ?? '', FILTER_VALIDATE_EMAIL) ? $in['mail_test_to'] : $in['mail_from'];
        try {
            \Illuminate\Support\Facades\Mail::to($to)->send(new \App\Mail\SocialMail(
                'Test d’envoi MX-CONNECT', 'L’envoi d’e-mails fonctionne',
                ['Cet e-mail confirme que MX-CONNECT peut envoyer des e-mails depuis ' . $in['mail_host'] . '.'],
                null, null, 'E-mail de test envoyé depuis la page d’installation.',
            ));
        } catch (\Throwable $e) {
            return [['Test d’envoi', false, "Échec : {$e->getMessage()}\nVérifiez le serveur (souvent mail.votre-domaine), le port (465 SSL ou 587 TLS), l’adresse complète et le mot de passe de la boîte e-mail créée dans le panneau LWS. Rien n’a été enregistré."]];
        }

        $this->writeEnv([
            'MAIL_MAILER' => 'smtp', 'MAIL_SCHEME' => $scheme, 'MAIL_HOST' => $in['mail_host'], 'MAIL_PORT' => (string) $port,
            'MAIL_USERNAME' => (string) ($in['mail_username'] ?? ''), 'MAIL_PASSWORD' => $password,
            'MAIL_FROM_ADDRESS' => $in['mail_from'], 'MAIL_FROM_NAME' => $fromName,
        ]);
        @unlink(base_path('bootstrap/cache/config.php'));

        return [['E-mails', true, "Test envoyé à {$to} puis paramètres enregistrés. Vérifiez la boîte de réception (et les indésirables), puis cliquez sur « Appliquer la mise à jour »."]];
    }

    /**
     * Dernières erreurs du journal (message + fichier:ligne uniquement), pour
     * diagnostiquer une page « 500 » sans accès au serveur. Aucune valeur de .env affichée.
     */
    protected function recentErrors(): array
    {
        $files = glob(storage_path('logs/*.log')) ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $found = [];
        foreach (array_slice($files, 0, 2) as $file) {
            $size = filesize($file);
            $fh = fopen($file, 'r');
            fseek($fh, max(0, $size - 400000));
            $chunk = (string) stream_get_contents($fh);
            fclose($fh);
            foreach (array_reverse(preg_split('/\R/', $chunk)) as $line) {
                if (! preg_match('/(ERROR|CRITICAL|EMERGENCY)/', $line)) {
                    continue;
                }
                $json = json_decode($line, true);
                if (is_array($json)) {
                    $msg = (string) ($json['message'] ?? '');
                    $when = (string) ($json['datetime'] ?? '');
                    $where = (string) ($json['context']['exception']['file'] ?? '');
                } elseif (preg_match('/^\[(.*?)\] \w+\.\w+: (.*?)(?: \{|$)/', $line, $m)) {
                    [$when, $msg, $where] = [$m[1], $m[2], ''];
                } else {
                    continue;
                }
                // Masque les éventuels secrets (mots de passe, hachages, jetons).
                $msg = preg_replace(['/\$2y\$\d+\$[^\s,)]+/', '/(password|token|secret)\S*/i'], ['[masqué]', '$1=[masqué]'], $msg);
                $found[] = [mb_substr($when, 0, 19), false, mb_strimwidth($msg, 0, 400, '…') . ($where ? "\n" . str_replace(base_path() . '/', '', $where) : '')];
                if (count($found) >= 6) {
                    break 2;
                }
            }
        }

        return $found ?: [['Journal', true, 'Aucune erreur récente.']];
    }

    /** Remplace (ou ajoute) des clés dans .env en préservant le reste du fichier. */
    protected function writeEnv(array $pairs): void
    {
        $file = base_path('.env');
        $env = is_file($file) ? file_get_contents($file) : '';

        foreach ($pairs as $key => $value) {
            $value = (string) $value;
            if ($value === '' || preg_match('/^[A-Za-z0-9_.\-@:\/+=]+$/', $value)) {
                $quoted = $value;
            } elseif (! str_contains($value, "'")) {
                $quoted = "'" . $value . "'";             // littéral, sans interpolation
            } else {
                $quoted = '"' . addcslashes($value, '"\\$') . '"';
            }
            $line = $key . '=' . $quoted;
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
            $env = preg_match($pattern, $env)
                ? preg_replace_callback($pattern, fn () => $line, $env)
                : rtrim($env) . "\n" . $line . "\n";
        }

        if (@file_put_contents($file, $env, LOCK_EX) === false) {
            throw new \RuntimeException("Impossible d'écrire le fichier .env (droits d'écriture ?).");
        }
    }

    protected function guard(Request $request): void
    {
        $expected = (string) config('mxconnect.install_token');
        $given = (string) $request->input('token');

        if (strlen($expected) < 24 || ! hash_equals($expected, $given)) {
            abort(404);
        }
    }

    /** État courant de l'installation. */
    protected function state(): array
    {
        try {
            DB::connection('central')->getPdo();
            $installed = Schema::connection('central')->hasTable('network_users');
            $mutuals = $installed ? DB::connection('central')->table('mutuals')->count() : 0;
        } catch (\Throwable) {
            $installed = false;
            $mutuals = 0;
        }

        return [
            'installed' => $installed,
            'mutuals'   => $mutuals,
            'db_ok'     => isset($installed) && $this->dbReachable(),
            'mail'      => [
                'ready' => \App\Support\MailStatus::configured(),
                'host' => config('mail.mailers.smtp.host'), 'port' => config('mail.mailers.smtp.port'),
                'scheme' => config('mail.mailers.smtp.scheme'), 'username' => config('mail.mailers.smtp.username'),
                'from' => config('mail.from.address'), 'from_name' => config('mail.from.name'),
            ],
            'cron_url'  => strlen((string) config('mxconnect.cron_token')) >= 24
                ? rtrim(config('app.url'), '/') . '/_cron?token=' . config('mxconnect.cron_token') : null,
            'db'        => [
                'host' => config('database.connections.central.host'),
                'port' => config('database.connections.central.port'),
                'database' => config('database.connections.central.database'),
                'username' => config('database.connections.central.username'),
            ],
        ];
    }

    protected function dbReachable(): bool
    {
        try {
            DB::connection('central')->getPdo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Liste de contrôles [libellé, ok, détail]. */
    protected function checks(): array
    {
        clearstatcache(true);
        $c = [];
        $c[] = ['PHP ≥ 8.4.1', version_compare(PHP_VERSION, '8.4.1', '>='), PHP_VERSION];
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'xmlwriter', 'dom', 'ctype', 'fileinfo', 'bcmath', 'intl', 'zip', 'gd', 'curl'] as $ext) {
            $c[] = ["Extension $ext", extension_loaded($ext), extension_loaded($ext) ? 'chargée' : 'MANQUANTE — à activer dans le panneau (Configuration PHP)'];
        }
        $c[] = ['Clé APP_KEY', filled(config('app.key')), filled(config('app.key')) ? 'définie' : 'à générer (bouton ci-dessous)'];
        $c[] = ['APP_DEBUG désactivé', ! config('app.debug'), config('app.debug') ? 'mettre APP_DEBUG=false en production' : 'OK'];
        foreach ([storage_path(), storage_path('framework/cache'), storage_path('framework/sessions'), storage_path('framework/views'), storage_path('logs'), base_path('bootstrap/cache')] as $dir) {
            $c[] = ['Écriture ' . str_replace(base_path() . '/', '', $dir), is_writable($dir), is_writable($dir) ? 'OK' : 'droits 755/775 requis'];
        }
        try {
            $pdo = DB::connection('central')->getPdo();
            $c[] = ['Base centrale', true, DB::connection('central')->getDatabaseName() . ' (' . $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) . ')'];
        } catch (\Throwable $e) {
            $c[] = ['Base centrale', false, 'connexion impossible : ' . $e->getMessage()];
        }
        $mode = config('mxconnect.tenant_db_mode');
        $c[] = ['Mode des bases mutuelles', true, $mode === 'manual'
            ? 'manual — chaque base est créée à l\'avance dans le panneau LWS'
            : 'auto — l\'utilisateur MySQL doit pouvoir faire CREATE DATABASE (VPS)'];
        $c[] = ['Cache / sessions / file', true, config('cache.default') . ' / ' . config('session.driver') . ' / ' . config('queue.default')];
        $link = public_path('storage');
        $c[] = ['Lien public/storage', is_link($link) || is_dir($link), is_link($link) || is_dir($link) ? 'présent' : 'absent (créé à l\'installation)'];

        return $c;
    }

    /** storage:link, avec repli si les liens symboliques sont interdits. */
    protected function storageLink(): array
    {
        $link = public_path('storage');
        $target = storage_path('app/public');
        File::ensureDirectoryExists($target);

        if (is_link($link) || is_dir($link)) {
            return ['Lien public/storage', true, 'déjà présent'];
        }
        if (@symlink($target, $link)) {
            return ['Lien public/storage', true, "$link → $target"];
        }

        return ['Lien public/storage', true, 'liens symboliques interdits : les fichiers publics sont servis par la route /storage (repli automatique)'];
    }
}
