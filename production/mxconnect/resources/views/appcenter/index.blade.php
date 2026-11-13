@php
  /** App Center (2026-11-13) — page publique du domaine principal. */
  $v = config('mxconnect.asset_version');
  $mut = $apps['mutuelle'] ?? null; $soc = $apps['social'] ?? null;
  $store = \App\Support\AndroidApp::storeUrl();
  $fmtDate = fn ($a) => $a?->released_on ? $a->released_on->translatedFormat('d F Y') : null;
  $cfg = [
      'social' => $soc ? ['app' => 'social', 'name' => $soc->name, 'endpoint' => route('appcenter.event'), 'startUrl' => '/app?source=pwa',
          'note' => $soc->guide_note, 'apk' => $apk['social'] ?? null, 'store' => $store, 'qr' => $qr['social'] ?? null] : null,
      'mutuelle' => $mut ? ['app' => 'mutuelle', 'name' => $mut->name, 'endpoint' => route('appcenter.event'), 'note' => $mut->guide_note,
          'apk' => $apk['mutuelle'] ?? null, 'store' => null, 'qr' => $qr['mutuelle'] ?? null] : null,
  ];
  $ico = [
      'pc' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
      'android' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.6 9.48l1.84-3.18a.38.38 0 0 0-.66-.38l-1.86 3.22A11.4 11.4 0 0 0 12 8.2c-1.77 0-3.4.37-4.92.94L5.22 5.92a.38.38 0 0 0-.66.38L6.4 9.48A10.8 10.8 0 0 0 1 18h22a10.8 10.8 0 0 0-5.4-8.52zM7 15.25a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5zm10 0a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5z"/></svg>',
  ];
@endphp
<!DOCTYPE html>
<html lang="fr" data-app="social" data-app-install data-app-endpoint="{{ route('appcenter.event') }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>MX-CONNECT — App Center</title>
  <meta name="description" content="Installez MX-CONNECT MUTUELLE et MX-CONNECT SOCIAL sur votre ordinateur ou votre téléphone Android.">
  <meta name="theme-color" content="#0B2559">
  <link rel="icon" href="/brand/favicon.png" type="image/png">
  <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
  {{-- Le manifeste du réseau social : c'est l'application installable directement depuis cette page. --}}
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="preload" href="/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="/css/mx-apps.css?v={{ $v }}">
</head>
<body class="ac">
<a class="ac-skip" href="#applications">Aller aux applications</a>

<header class="ac-head">
  <div class="ac-wrap ac-head__in">
    <a class="ac-logo" href="{{ route('appcenter.index') }}" aria-label="MX-CONNECT App Center">
      <img src="/brand/mx-mark-128.webp" alt="" width="40" height="40">
      <span>M<b>X</b>-CONNECT<small>App Center</small></span>
    </a>
    <button class="ac-burger" type="button" aria-label="Ouvrir le menu" aria-controls="ac-nav" aria-expanded="false"><span></span></button>
    <nav class="ac-nav" id="ac-nav" aria-label="Navigation principale">
      <a href="{{ route('home') }}">Accueil</a>
      <a href="#applications" class="is-active">Nos applications</a>
      <a href="#fonctionnalites">Fonctionnalités</a>
      <a href="#assistance">Assistance</a>
      <a class="ac-btn ac-btn--navy" href="{{ route('home.login') }}">Connexion</a>
    </nav>
  </div>
</header>

<main id="contenu">
  <section class="ac-hero">
    <div class="ac-wrap ac-hero__in">
      <span class="ac-kicker"><i></i> MX-CONNECT · App Center</span>
      <h1>BIENVENUE DANS <span>L’ÉCOSYSTÈME MX-CONNECT</span></h1>
      <p class="ac-hero__sub">Toutes vos solutions numériques de gestion mutualiste et de connexion sociale dans un seul écosystème.</p>
      <p class="ac-hero__txt">Découvrez nos applications et installez-les directement sur votre ordinateur ou votre smartphone Android pour profiter pleinement de l’expérience MX-CONNECT.</p>
      <div class="ac-envbar" aria-live="polite">
        <span class="ac-chip" id="ac-env-os">{!! $ico['pc'] !!} Détection de votre appareil…</span>
        <span class="ac-chip" id="ac-env-br" hidden></span>
      </div>
    </div>
  </section>

  <div class="ac-wrap">
    <section class="ac-apps" id="applications" aria-label="Nos applications">
      @foreach (['mutuelle' => $mut, 'social' => $soc] as $key => $app)
        @continue(! $app)
        <article class="ac-card ac-card--{{ $key }} @if (! $app->enabled) is-off @endif" id="{{ $key }}" data-app-card="{{ $key }}">
          <div class="ac-card__visual" aria-hidden="true">
            @if ($key === 'mutuelle')
              <span class="ac-float ac-float--a"><b style="background:#2E9E4F">✓</b> Droits vérifiés</span>
              <span class="ac-float ac-float--b"><b style="background:#2F6FDB">≡</b> Comptes SYSCOHADA</span>
              <div class="ac-laptop"><div class="ac-laptop__screen"><img src="/brand/appcenter/mutuelle-ecran.jpg" alt="" width="960" height="600" loading="lazy"></div><div class="ac-laptop__base"></div></div>
            @else
              <span class="ac-float ac-float--a"><b style="background:#E07B24">♥</b> Réactions</span>
              <span class="ac-float ac-float--b"><b style="background:#2E9E4F">✉</b> Messagerie</span>
              <div class="ac-phone"><img src="/brand/appcenter/social-ecran.jpg" alt="" width="390" height="844" loading="lazy"></div>
            @endif
            <span class="ac-sample">Aperçu · données fictives</span>
          </div>
          <div class="ac-card__body">
            <div class="ac-card__top">
              <span class="ac-card__icon"><img src="{{ route('appcenter.icon', $key) }}" alt="" width="38" height="38"></span>
              <div>
                <h2>{{ $app->name }}</h2>
                @if ($app->tagline)<p class="ac-card__tag">{{ $app->tagline }}</p>@endif
              </div>
            </div>
            <p class="ac-card__desc">{{ $app->description }}</p>
            <ul class="ac-feats">
              @foreach ((array) $app->features as $f)<li>{{ $f }}</li>@endforeach
            </ul>
            <div class="ac-meta">
              @if ($app->enabled)<span class="ac-status" data-installed-label>Disponible</span>@else<span class="ac-status ac-status--off">Bientôt disponible</span>@endif
              <span>Version <b>{{ $app->version }}</b></span>
              @if ($fmtDate($app))<span>Mise à jour le <b>{{ $fmtDate($app) }}</b></span>@endif
              @if ($key === 'mutuelle')<span>Réservée au personnel des mutuelles</span>@endif
            </div>
            <div class="ac-actions">
              <button type="button" class="ac-btn ac-btn--blue" data-install="{{ $key }}" data-target="pc" @disabled(! $app->enabled)>{!! $ico['pc'] !!} INSTALLER SUR PC</button>
              <button type="button" class="ac-btn ac-btn--green" data-install="{{ $key }}" data-target="android" @disabled(! $app->enabled)>{!! $ico['android'] !!} INSTALLER SUR ANDROID</button>
            </div>
            <p class="ac-muted" style="margin:0">
              @if ($key === 'social')
                Ou <a href="{{ route('social.home') }}">utiliser le réseau social dans le navigateur</a>.
              @else
                Déjà installée ? <a href="{{ route('home.login') }}">Se connecter à ma mutuelle</a>.
              @endif
            </p>
          </div>
        </article>
      @endforeach
    </section>

    <section class="ac-sec" id="fonctionnalites">
      <h2 class="ac-title">Deux applications, un même écosystème</h2>
      <p class="ac-lead">Chaque application s’installe séparément, avec son propre nom, son icône et sa fenêtre. Vos données restent cloisonnées : chaque mutuelle n’accède qu’à ses propres données, et aucune donnée médicale ne circule sur le réseau social.</p>
      <div class="ac-table-scroll">
        <table class="ac-compare">
          <thead><tr><th scope="col"></th><th scope="col">{{ $mut?->name ?? 'MX-CONNECT MUTUELLE' }}</th><th scope="col">{{ $soc?->name ?? 'MX-CONNECT SOCIAL' }}</th></tr></thead>
          <tbody>
            <tr><td>Pour qui</td><td>Le personnel des mutuelles de santé</td><td>Citoyens, membres, organisations, associations</td></tr>
            <tr><td>Rôle</td><td>Gestion des mutuelles, adhérents, cotisations, prestations</td><td>Réseau social, publications et actualités</td></tr>
            <tr><td>Suivi</td><td>Rapports, états comptables, statistiques</td><td>Messagerie, groupes et communautés</td></tr>
            <tr><td>Sécurité</td><td>Double authentification, rôles séparés, contrôle à quatre yeux</td><td>Interactions et notifications maîtrisées, signalements</td></tr>
            <tr><td>Adresse</td><td>Celle de votre mutuelle (votre-mutuelle.{{ config('mxconnect.tenant_base_domain') }})</td><td>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="ac-sec" aria-labelledby="ac-how">
      <h2 class="ac-title" id="ac-how">Comment ça marche</h2>
      <p class="ac-lead">Les applications MX-CONNECT sont des applications web installables : pas de fichier exécutable à télécharger, des mises à jour automatiques à chaque ouverture.</p>
      <div class="ac-steps">
        <div class="ac-step"><span class="ac-step__n">1</span><h3>Choisissez l’application</h3><p>MX-CONNECT MUTUELLE pour le personnel des mutuelles, MX-CONNECT SOCIAL pour tous.</p></div>
        <div class="ac-step"><span class="ac-step__n">2</span><h3>Touchez « Installer »</h3><p>Votre navigateur ouvre sa fenêtre d’installation, ou un guide adapté à votre appareil s’affiche.</p></div>
        <div class="ac-step"><span class="ac-step__n">3</span><h3>Ouvrez-la comme une application</h3><p>Une icône apparaît sur le bureau, dans le menu Démarrer ou sur l’écran d’accueil.</p></div>
      </div>
    </section>

    <section class="ac-sec" id="assistance">
      <div class="ac-help">
        <div>
          <h2>Besoin d’aide pour installer ?</h2>
          <p>Notre équipe vous accompagne. Les applications fonctionnent avec Chrome et Edge sur ordinateur, Chrome et Samsung Internet sur Android, et Safari sur iPhone (ajout à l’écran d’accueil).</p>
          <p class="ac-muted" style="color:#B8C8E6">Firefox sur ordinateur ne permet pas l’installation : utilisez Chrome ou Edge.</p>
        </div>
        <ul>
          <li><a href="{{ route('social.help') }}">Centre d’aide</a><span>Questions fréquentes, signaler un problème</span></li>
          <li><a href="mailto:contact@araild.com">contact@araild.com</a><span>Réponse par e-mail</span></li>
          <li><a href="tel:+237676460158">+237 676 460 158</a><span>ARAILD · Yaoundé, Cameroun</span></li>
        </ul>
      </div>
      <div class="ac-faq" style="margin-top:22px">
        <details><summary>Est-ce un logiciel à télécharger ?</summary><p>Non. Ce sont des applications web installables (PWA) : le navigateur crée une application avec sa propre fenêtre et son icône. Aucun fichier .exe n’est téléchargé, et les mises à jour sont automatiques.</p></details>
        <details><summary>Pourquoi dois-je choisir ma mutuelle ?</summary><p>Chaque mutuelle dispose de sa propre adresse et de ses propres données. L’application MX-CONNECT MUTUELLE s’installe donc depuis l’adresse de votre mutuelle, et s’ouvre directement sur sa page de connexion.</p></details>
        <details><summary>Que deviennent mes données ?</summary><p>Rien n’est stocké sur l’appareil en dehors des fichiers d’affichage (styles, logo). Les statistiques d’installation sont anonymes : ni compte, ni adresse IP, ni donnée de santé.</p></details>
        <details><summary>Comment désinstaller ?</summary><p>Sur ordinateur, ouvrez l’application puis le menu ⋮ → « Désinstaller ». Sur Android, appui long sur l’icône → « Désinstaller ».</p></details>
      </div>
    </section>
  </div>
</main>

<footer class="ac-foot">
  <div class="ac-wrap ac-foot__in">
    <span>© {{ date('Y') }} MX-CONNECT · ARAILD — Action pour la Recherche et l’Appui aux Initiatives Locales de Développement</span>
    <span><a href="{{ route('home') }}">Présentation de la plateforme</a> · <a href="{{ route('social.privacy') }}">Confidentialité</a> · <a href="{{ route('social.help') }}">Aide</a></span>
  </div>
</footer>

<template id="tpl-mutual">
  <p>L’application s’installe depuis l’adresse de <b>votre</b> mutuelle. Indiquez son nom ou son identifiant (par exemple <i>mutueltest</i>, tiré de l’adresse <i>mutueltest.{{ config('mxconnect.tenant_base_domain') }}</i>).</p>
  <form method="post" action="{{ route('appcenter.mutual') }}" class="ac-mutual-form">
    @csrf
    <input type="hidden" name="appareil" value="pc">
    <div class="ac-field">
      <label for="ac-mutuelle">Votre mutuelle</label>
      <input id="ac-mutuelle" name="mutuelle" list="ac-mutuelles" autocomplete="organization" required maxlength="120" value="{{ old('mutuelle') }}" placeholder="Nom ou identifiant de la mutuelle">
      @if ($mutuals->count() && $mutuals->count() <= 300)
        <datalist id="ac-mutuelles">@foreach ($mutuals as $m)<option value="{{ $m->name }}">@endforeach</datalist>
      @endif
    </div>
    @error('mutuelle')<p class="ac-error" role="alert">{{ $message }}</p>@enderror
    <button class="ac-btn ac-btn--green" type="submit" style="width:100%">Continuer vers l’installation</button>
    <p class="ac-muted">Vous serez dirigé vers la page d’installation sécurisée de votre mutuelle.</p>
  </form>
</template>

<script src="/js/mx-appcenter.js?v={{ $v }}"></script>
<script>
(function () {
  var M = window.MXApp, env = M.env, cfg = @json($cfg);
  M.registerWorker('/sw-social.js');

  // Menu mobile
  var burger = document.querySelector('.ac-burger'), nav = document.getElementById('ac-nav');
  burger.addEventListener('click', function () { var o = nav.classList.toggle('is-open'); burger.setAttribute('aria-expanded', o ? 'true' : 'false'); });
  nav.addEventListener('click', function (e) { if (e.target.closest('a')) { nav.classList.remove('is-open'); burger.setAttribute('aria-expanded', 'false'); } });

  // Appareil détecté
  var os = document.getElementById('ac-env-os'), br = document.getElementById('ac-env-br');
  os.lastChild.textContent = ' ' + M.labels.os[env.os];
  if (env.mobile) os.firstChild.outerHTML = @json($ico['android']);
  br.hidden = false; br.textContent = M.labels.browser[env.browser] + (env.chromium && !env.mobile ? ' · installation directe possible' : '');

  // Bouton conseillé selon l'appareil
  document.querySelectorAll('[data-install]').forEach(function (b) {
    if ((b.dataset.target === 'android' && env.os === 'android') || (b.dataset.target === 'pc' && !env.mobile)) b.classList.add('is-suggested');
  });

  // Réseau social déjà installé ?
  M.checkInstalled('social', function (yes) {
    var l = document.querySelector('#social [data-installed-label]'); if (yes && l) l.textContent = 'Installée sur cet appareil';
  });

  function mutualForm(target) {
    var body = M.open('Installer ' + cfg.mutuelle.name, document.getElementById('tpl-mutual').innerHTML);
    body.querySelector('input[name=appareil]').value = target;
    var input = body.querySelector('#ac-mutuelle'); if (input) input.focus();
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-install]'); if (!b || b.disabled) return;
    var key = b.dataset.install, target = b.dataset.target, c = cfg[key]; if (!c) return;
    if (key === 'mutuelle') {
      M.track(c.endpoint, 'mutuelle', target === 'android' ? 'click_android' : 'click_pc');
      if ((target === 'android' && env.os !== 'android') || (target === 'pc' && env.mobile)) {
        // Mauvais appareil : guide (QR code pour passer du PC au téléphone).
        var cc = Object.assign({}, c, { target: target }); M.guide(cc); return;
      }
      mutualForm(target); return;
    }
    M.install(Object.assign({}, c, { target: target }));
  });

  @if ($errors->has('mutuelle'))
    mutualForm(@json(old('appareil', 'pc')));
  @endif
  @if ($suggest)
    var card = document.getElementById(@json($suggest)); if (card) card.scrollIntoView({ block: 'start' });
  @endif
})();
</script>
</body>
</html>
