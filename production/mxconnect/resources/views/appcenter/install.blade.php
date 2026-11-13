@php
  /** Page d'installation de MX-CONNECT MUTUELLE sur le sous-domaine d'une mutuelle (2026-11-13). */
  $v = config('mxconnect.asset_version');
  $cfg = ['app' => 'mutuelle', 'name' => $mutualName . ' · ' . $app->name, 'endpoint' => '/installer/evenement', 'startUrl' => '/?source=pwa',
          'note' => $app->guide_note, 'apk' => $apk, 'store' => null, 'qr' => $qr];
@endphp
<!DOCTYPE html>
<html lang="fr" data-app="mutuelle" data-app-install data-app-endpoint="/installer/evenement">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex">
  <title>Installer {{ $app->name }} · {{ $mutualName }}</title>
  <meta name="theme-color" content="#0B2559">
  <link rel="icon" href="/brand/favicon.png" type="image/png">
  <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
  <link rel="manifest" href="/app-mutuelle.webmanifest">
  <link rel="preload" href="/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="/css/mx-apps.css?v={{ $v }}">
</head>
<body class="ac ac-install">
<header class="ac-head">
  <div class="ac-wrap ac-head__in">
    <span class="ac-logo"><img src="/brand/mx-mark-128.webp" alt="" width="40" height="40"><span>M<b>X</b>-CONNECT<small>{{ $mutualName }}</small></span></span>
    <nav class="ac-nav" style="display:flex;position:static;box-shadow:none;border:0;padding:0;flex-direction:row" aria-label="Navigation">
      <a class="ac-btn ac-btn--ghost" href="/connexion">Connexion</a>
    </nav>
  </div>
</header>
<main class="ac-install__main">
  <div class="ac-wrap ac-install__grid">
    <div>
      <span class="ac-kicker">Application de votre mutuelle</span>
      <h1>Installer {{ $app->name }}</h1>
      <p class="ac-badge"><img src="/brand/mx-mark-128.webp" alt="" width="22" height="22"> {{ $mutualName }}</p>
      <p>{{ $app->description }}</p>
      <p class="ac-muted">Cette application est propre à <b>{{ $mutualName }}</b> : elle s’ouvre directement sur l’espace de votre mutuelle, avec votre connexion habituelle et la double authentification. Aucune donnée de gestion n’est enregistrée sur l’appareil.</p>
      <div class="ac-actions">
        <button type="button" class="ac-btn ac-btn--blue" data-target="pc">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg> INSTALLER SUR PC</button>
        <button type="button" class="ac-btn ac-btn--green" data-target="android">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.6 9.48l1.84-3.18a.38.38 0 0 0-.66-.38l-1.86 3.22A11.4 11.4 0 0 0 12 8.2c-1.77 0-3.4.37-4.92.94L5.22 5.92a.38.38 0 0 0-.66.38L6.4 9.48A10.8 10.8 0 0 0 1 18h22a10.8 10.8 0 0 0-5.4-8.52zM7 15.25a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5zm10 0a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5z"/></svg> INSTALLER SUR ANDROID</button>
      </div>
      <p class="ac-meta" style="margin-top:14px"><span class="ac-status" id="ac-state">Disponible</span><span>Version <b>{{ $app->version }}</b></span>
        @if ($app->released_on)<span>Mise à jour le <b>{{ $app->released_on->translatedFormat('d F Y') }}</b></span>@endif
        <span id="ac-device"></span></p>
      <p class="ac-muted">Vous préférez le navigateur ? <a href="/connexion">Se connecter sans installer</a> · <a href="{{ rtrim(config('app.url'), '/') }}/applications">Toutes les applications MX-CONNECT</a></p>
    </div>
    <div class="ac-card ac-card--mutuelle" style="transform:none">
      <div class="ac-card__visual" style="height:300px" aria-hidden="true">
        <span class="ac-float ac-float--a"><b style="background:#2E9E4F">✓</b> Droits vérifiés</span>
        <span class="ac-float ac-float--b"><b style="background:#2F6FDB">≡</b> Rapports</span>
        <div class="ac-laptop"><div class="ac-laptop__screen"><img src="/brand/appcenter/mutuelle-ecran.jpg" alt="" width="960" height="600"></div><div class="ac-laptop__base"></div></div>
        <span class="ac-sample">Aperçu · données fictives</span>
      </div>
    </div>
  </div>
</main>
<footer class="ac-foot" style="margin-top:0">
  <div class="ac-wrap ac-foot__in"><span>{{ $mutualName }} · propulsé par MX-CONNECT (ARAILD)</span><span><a href="/connexion">Connexion</a></span></div>
</footer>
<script src="/js/mx-appcenter.js?v={{ $v }}"></script>
<script>
(function () {
  var M = window.MXApp, env = M.env, cfg = @json($cfg), preferred = @json($target);
  M.registerWorker('/sw-mutuelle.js');
  document.getElementById('ac-device').textContent = 'Appareil : ' + M.labels.os[env.os] + ' · ' + M.labels.browser[env.browser];
  document.querySelectorAll('[data-target]').forEach(function (b) {
    var t = b.dataset.target;
    if ((t === 'android' && env.os === 'android') || (t === 'pc' && !env.mobile)) b.classList.add('is-suggested');
    b.addEventListener('click', function () { M.install(Object.assign({}, cfg, { target: t })); });
  });
  M.checkInstalled('mutuelle', function (yes) { if (yes) document.getElementById('ac-state').textContent = 'Installée sur cet appareil'; });
  // Arrivée depuis l'App Center : le bouton choisi est mis en avant (le navigateur exige un clic pour ouvrir sa fenêtre).
  var b = document.querySelector('[data-target="' + preferred + '"]'); if (b) b.focus();
})();
</script>
</body>
</html>
