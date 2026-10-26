<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Installation · MX-CONNECT</title>
<style>
  :root { --navy:#0B2559; --green:#43B75D; --ink:#0B1F3A; --clay:#C0392B; --paper:#F7FAF8; --line:#dfe6e2; }
  * { box-sizing: border-box; }
  body { margin:0; font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; color:var(--ink); background:var(--paper); }
  header { background:var(--navy); color:#fff; padding:18px 16px; }
  header .in, main { max-width:880px; margin:0 auto; }
  header b { color:var(--green); }
  main { padding:24px 16px 60px; }
  h2 { font-size:17px; margin:28px 0 10px; }
  table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--line); border-radius:8px; overflow:hidden; }
  td { padding:7px 10px; border-top:1px solid var(--line); vertical-align:top; font-size:14px; }
  tr:first-child td { border-top:0; }
  .ok { color:var(--green); font-weight:600; } .ko { color:var(--clay); font-weight:600; }
  .card { background:#fff; border:1px solid var(--line); border-radius:8px; padding:16px; margin-top:12px; }
  label { display:block; font-size:13px; font-weight:600; margin:10px 0 4px; }
  input { width:100%; padding:9px 10px; border:1px solid #c8d2cc; border-radius:6px; font-size:14px; }
  button { margin-top:12px; padding:9px 16px; border:0; border-radius:6px; background:var(--navy); color:#fff; font-weight:600; cursor:pointer; }
  button.alt { background:#fff; color:var(--navy); border:1px solid var(--navy); }
  .row { display:flex; gap:10px; flex-wrap:wrap; }
  pre { white-space:pre-wrap; word-break:break-word; margin:4px 0 0; font-size:12px; color:#3c4a44; }
  .warn { background:#fff8e6; border:1px solid #f0d08a; padding:12px; border-radius:8px; font-size:14px; }
</style>
</head>
<body>
<header><div class="in"><img src="/brand/mx-mark-128.webp" alt="" width="34" height="34" style="vertical-align:middle;background:#fff;border-radius:8px;padding:2px;margin-right:8px"><strong>MX-<b>CONNECT</b></strong> · Installation &amp; mise à jour (hébergement mutualisé)</div></header>
<main>

@if ($log)
  <h2>Résultat</h2>
  <table>
    @foreach ($log as [$label, $ok, $out])
      <tr><td style="width:34%">{{ $label }}</td><td><span class="{{ $ok ? 'ok' : 'ko' }}">{{ $ok ? '✔' : '✖' }}</span><pre>{{ \Illuminate\Support\Str::limit($out, 4000) }}</pre></td></tr>
    @endforeach
  </table>
@endif

<h2>Contrôles</h2>
<table>
  @foreach ($checks as [$label, $ok, $detail])
    <tr><td style="width:34%">{{ $label }}</td><td><span class="{{ $ok ? 'ok' : 'ko' }}">{{ $ok ? '✔' : '✖' }}</span> {{ $detail }}</td></tr>
  @endforeach
</table>

@if (! filled(config('app.key')))
  <h2>Générer la clé</h2>
  <form method="POST" class="card">
    <input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="action" value="key">
    La clé est écrite dans le fichier .env. Recharger ensuite la page.
    <br><button>Générer APP_KEY</button>
  </form>
@elseif (! $state['db_ok'])
  <h2>Étape 1 · Base de données</h2>
  <form method="POST" class="card">
    <input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="action" value="database">
    Saisissez la base MySQL créée dans le panneau LWS (rubrique <em>Bases de données MySQL</em>).
    Les accès sont testés puis enregistrés dans le fichier .env — aucune édition manuelle.
    <div class="row">
      <div style="flex:3;min-width:200px"><label>Serveur (hôte)</label>
        <input name="db_host" required value="{{ request('db_host', $state['db']['host'] !== '<hote_mysql_lws>' ? $state['db']['host'] : '') }}" placeholder="ex. localhost ou mysqlXX.lwspanel.com"></div>
      <div style="flex:1;min-width:90px"><label>Port</label>
        <input name="db_port" value="{{ request('db_port', $state['db']['port'] ?: 3306) }}"></div>
    </div>
    <label>Nom de la base (avec le préfixe LWS)</label>
    <input name="db_database" required value="{{ request('db_database', str_starts_with((string) $state['db']['database'], '<') ? '' : $state['db']['database']) }}">
    <label>Utilisateur</label>
    <input name="db_username" required value="{{ request('db_username', str_starts_with((string) $state['db']['username'], '<') ? '' : $state['db']['username']) }}">
    <label>Mot de passe</label>
    <input name="db_password" type="password" autocomplete="new-password">
    <label>Adresse du site</label>
    <input name="app_url" value="{{ request('app_url', config('app.url')) }}" placeholder="https://www.m.araild.com">
    <button>Tester et enregistrer</button>
  </form>
@elseif (! $state['installed'])
  <h2>Étape 2 · Installation</h2>
  <form method="POST" class="card">
    <input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="action" value="install">
    Crée les tables de la base centrale, les données de référence (pays, devises, langues) et le compte super-admin.
    <label>E-mail du super-admin</label>
    <input name="admin_email" type="email" required value="{{ request('admin_email', 'admin@' . config('mxconnect.tenant_base_domain')) }}">
    <label>Mot de passe du super-admin (12 caractères minimum)</label>
    <input name="admin_password" type="password" required minlength="12" autocomplete="new-password">
    <button>Installer</button>
  </form>
@else
  <h2>Application installée · {{ $state['mutuals'] }} mutuelle(s)</h2>
  <div class="row">
    @foreach (['update' => 'Appliquer la mise à jour (migrations + caches)', 'clear' => 'Vider les caches', 'health' => 'Santé des bases mutuelles', 'logs' => 'Voir les dernières erreurs', 'demo' => 'Réseau social : ajouter les données de démonstration', 'demo_remove' => 'Réseau social : supprimer les données de démonstration'] as $a => $txt)
      <form method="POST">
        <input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="action" value="{{ $a }}">
        <button class="{{ $a === 'update' ? '' : 'alt' }}">{{ $txt }}</button>
      </form>
    @endforeach
  </div>

  <h2>Administrateur réseau : double authentification perdue</h2>
  <form method="POST" class="card">
    <input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="action" value="mfa_reset">
    Téléphone perdu ou application d’authentification supprimée : réinitialisez la double authentification d’un administrateur du réseau. Il en configurera une nouvelle à sa prochaine connexion.
    <label>E-mail de l’administrateur</label>
    <input name="admin_email" type="email" required>
    <button class="alt">Réinitialiser la double authentification</button>
  </form>

  <h2>Adresse du site</h2>
  <form method="POST" class="card">
    <input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="action" value="site">
    Adresse publique du réseau social (sans « www » si ce sous-domaine n’existe pas).
    <label>Adresse du site</label>
    <input name="app_url" required value="{{ config('app.url') }}" placeholder="https://m.araild.com">
    <label>E-mail qui reçoit les demandes de démonstration (page d’accueil)</label>
    <input name="contact_email" type="email" value="{{ config('mxconnect.contact_email') }}" placeholder="contact@araild.com">
    <button class="alt">Enregistrer</button>
  </form>

  <h2>E-mails (SMTP) @if ($state['mail']['ready'])<small style="color:#2f7a2f">— configurés</small>@else<small style="color:#b54708">— non configurés</small>@endif</h2>
  <form method="POST" class="card">
    <input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="action" value="mail">
    Créez d’abord une boîte e-mail dans le panneau LWS (ex. <em>no-reply@votre-domaine</em>). Un e-mail de test est envoyé : les paramètres ne sont enregistrés que si l’envoi réussit.
    <div class="row">
      <div style="flex:3;min-width:200px"><label>Serveur SMTP</label><input name="mail_host" required value="{{ $state['mail']['host'] !== '127.0.0.1' ? $state['mail']['host'] : '' }}" placeholder="mail.araild.com"></div>
      <div style="flex:1;min-width:90px"><label>Port</label><input name="mail_port" value="{{ $state['mail']['port'] ?: 465 }}"></div>
      <div style="flex:1;min-width:120px"><label>Sécurité</label><select name="mail_security"><option value="ssl" @selected($state['mail']['scheme'] !== 'smtp')>SSL (465)</option><option value="tls" @selected($state['mail']['scheme'] === 'smtp')>TLS (587)</option></select></div>
    </div>
    <label>Identifiant (adresse e-mail complète)</label><input name="mail_username" value="{{ $state['mail']['username'] }}" autocomplete="off">
    <label>Mot de passe de la boîte e-mail @if ($state['mail']['ready'])(laisser vide pour conserver)@endif</label><input name="mail_password" type="password" autocomplete="new-password">
    <div class="row">
      <div style="flex:2;min-width:200px"><label>Adresse d’expédition</label><input name="mail_from" type="email" required value="{{ $state['mail']['from'] }}"></div>
      <div style="flex:1;min-width:150px"><label>Nom d’expéditeur</label><input name="mail_from_name" value="{{ $state['mail']['from_name'] ?: 'MX-CONNECT' }}"></div>
    </div>
    <label>Envoyer le test à (facultatif)</label><input name="mail_test_to" type="email" placeholder="votre adresse personnelle">
    <button>Tester et enregistrer</button>
  </form>

  @if ($state['cron_url'])
    <h2>Étape 3 · Tâches planifiées</h2>
    <div class="card">
      Dans le panneau LWS (<em>Tâches Cron</em>), programmez l'appel de cette adresse <strong>toutes les 5 à 15 minutes</strong>
      (ou utilisez un service gratuit comme cron-job.org). Elle déclenche rappels, sauvegardes, facturation et notifications.
      <pre style="background:#f1f5f3;padding:8px;border-radius:6px;font-size:13px">{{ $state['cron_url'] }}</pre>
      Gardez cette adresse confidentielle.
    </div>
  @endif

  <h2>Étape 4 · Terminer</h2>
  <form method="POST" class="card">
    <input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="action" value="finish">
    Désactive cette page en effaçant le jeton d'installation du fichier .env (elle renverra 404).
    Pour une future mise à jour, il faudra remettre un jeton <code>INSTALL_TOKEN=</code> dans .env via le gestionnaire de fichiers.
    <br><button>Terminer et désactiver l'installateur</button>
  </form>
@endif

</main>
</body>
</html>
