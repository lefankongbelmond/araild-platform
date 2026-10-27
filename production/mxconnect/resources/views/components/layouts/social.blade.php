@props(['title' => null, 'active' => null, 'wide' => false, 'bare' => false])
@php
  $viewer = auth('member')->user();
  // Navigation « Lot 2 » : rubriques principales, puis domaines, puis espaces personnels.
  $nav = [
    ['fil', 'social.home', 'home', 'Accueil', null],
    ['decouvrir', 'social.discover', 'compass', 'Découvrir', null],
    ['organisations', 'social.mutuals', 'building', 'Organisations', null],
    ['projets', 'social.projects', 'folder', 'Projets', null],
    ['evenements', 'social.events', 'calendar', 'Événements', null],
    ['opportunites', 'social.opportunities', 'rocket', 'Opportunités', null],
    ['reels', 'social.reels', 'reel', 'Réels', null],
    ['groupes', 'social.groups', 'groups', 'Groupes', null],
    ['toutes', 'social.all', 'list', 'Toutes les publications', null],
  ];
  $mine = [
    ['pages', 'social.pages.mine', 'flag-page', 'Mes pages'],
    ['demandes', 'social.requests', 'briefcase', 'Mes candidatures'],
    ['adhesions', 'social.adhesions', 'file', 'Mes adhésions'],
    ['abonnements', 'social.following', 'star', 'Mes abonnements'],
    ['connexions', 'social.connections', 'users', 'Mes connexions'],
    ['enregistrements', 'social.saved', 'bookmark', 'Enregistrements'],
    ['annonceur', 'social.ads', 'megaphone', 'Publicité'],
  ];
  $soon = [];
  $unreadNotif = $viewer ? \App\Models\Social\SocialNotification::where('public_account_id', $viewer->id)->whereNull('read_at')->count() : 0;
  $unreadMsg = $viewer ? (int) \App\Models\Social\Conversation::where('public_account_id', $viewer->id)->sum('account_unread') : 0;
  // Documents légaux à valider (2026-11-09) : bandeau, la lecture reste possible.
  $legalPending = $viewer ? app(\App\Services\Social\LegalService::class)->pendingFor($viewer)->count() : 0;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @if ($viewer)<meta name="mx-mentions" content="{{ route('social.mentions') }}">@endif
  <meta name="theme-color" content="#0B2559">
  <title>{{ $title ? $title . ' · ' : '' }}MX-CONNECT</title>
  <meta name="description" content="MX-CONNECT — Ensemble pour une meilleure santé. Le réseau social des mutuelles de santé.">
  <link rel="icon" href="/brand/favicon.png" type="image/png">
  <link rel="alternate icon" href="/brand/favicon.ico">
  <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="preload" href="/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="/css/mx-social.css?v={{ config('mxconnect.asset_version') }}">
  <link rel="stylesheet" href="/css/mx-app.css?v={{ config('mxconnect.asset_version') }}">
  {{-- Mode application (2026-11-09) : application Android (TWA) ou site installé (PWA) → barre
       de navigation du bas des maquettes. Le site ouvert dans un navigateur ne change pas. --}}
  <script>(function(){try{var d=document.documentElement,s=window.sessionStorage,app=matchMedia('(display-mode: standalone)').matches||matchMedia('(display-mode: fullscreen)').matches||document.referrer.indexOf('android-app://')===0||(s&&s.getItem('mx_app')==='1');if(app){d.classList.add('is-app');if(s)s.setItem('mx_app','1');}}catch(e){}})();</script>
  {{ $head ?? '' }}
</head>
@php $light = \App\Support\SocialLoad::light(); @endphp
<body @if($light) data-light="1" @endif>
  <header class="mx-topbar">
    <div class="mx-topbar__in">
      <x-mx.logo :href="$viewer ? route('social.home') : url('/')" />
      <form class="mx-search" action="{{ route('social.search') }}" method="GET" role="search">
        <x-mx.icon name="search" :size="18" />
        <label class="mx-sr" for="mx-q">Rechercher</label>
        <input id="mx-q" name="q" value="{{ request('q') }}" placeholder="Rechercher des mutuelles, publications, membres…" autocomplete="off">
      </form>
      <div class="mx-top-actions">
        @if ($viewer)
          <a class="mx-iconbtn mx-show-m mx-web-only" href="{{ route('social.home') }}#composer" aria-label="Créer une publication"><x-mx.icon name="plus" :size="20" /></a>
        @endif
        <a class="mx-iconbtn mx-show-sm" href="{{ route('social.search') }}" aria-label="Rechercher"><x-mx.icon name="search" :size="20" /></a>
        @if ($viewer)
          <a class="mx-iconbtn mx-badge-wrap mx-apphead-only" href="{{ route('social.notifications') }}" aria-label="Notifications{{ $unreadNotif ? ' ('.$unreadNotif.' non lues)' : '' }}"><x-mx.icon name="bell" :size="20"/>@if($unreadNotif)<span class="mx-count">{{ $unreadNotif > 9 ? '9+' : $unreadNotif }}</span>@endif</a>
          <a class="mx-apphead-only" href="{{ route('social.app.menu') }}" aria-label="Mon profil"><x-mx.avatar :src="$viewer->avatarUrl()" :name="$viewer->name" size="sm" /></a>
        @endif
        @if ($viewer)
          <a class="mx-iconbtn mx-badge-wrap mx-hide-m" href="{{ route('social.messages') }}" aria-label="Messages{{ $unreadMsg ? ' ('.$unreadMsg.' non lus)' : '' }}"><x-mx.icon name="message" :size="18" />@if($unreadMsg)<span class="mx-count">{{ $unreadMsg > 9 ? '9+' : $unreadMsg }}</span>@endif</a>
          <a class="mx-iconbtn mx-badge-wrap mx-hide-m" href="{{ route('social.notifications') }}" aria-label="Notifications{{ $unreadNotif ? ' ('.$unreadNotif.' non lues)' : '' }}"><x-mx.icon name="bell" :size="18" />@if($unreadNotif)<span class="mx-count">{{ $unreadNotif > 9 ? '9+' : $unreadNotif }}</span>@endif</a>
          <a class="mx-btn mx-btn--primary mx-btn--sm mx-hide-m" href="{{ route('social.home') }}#composer"><x-mx.icon name="plus" :size="16"/> Publier</a>
          <details class="mx-menu mx-hide-m">
            <summary aria-label="Mon compte"><x-mx.avatar :src="$viewer->avatarUrl()" :name="$viewer->name" size="sm" class="mx-topavatar" /></summary>
            <div class="mx-menu__list">
              <div style="padding:8px 10px"><b>{{ $viewer->name }}</b><div class="mx-xs mx-muted">{{ $viewer->email ?: $viewer->phone }}</div></div>
              <a href="{{ route('social.user', $viewer) }}"><x-mx.icon name="user" :size="18"/> Mon profil</a>
              <a href="{{ route('social.saved') }}"><x-mx.icon name="bookmark" :size="18"/> Enregistrements</a>
              <a href="{{ route('social.connections') }}"><x-mx.icon name="users" :size="18"/> Mes connexions</a>
              <a href="{{ route('social.adhesions') }}"><x-mx.icon name="file" :size="18"/> Mes demandes d’adhésion</a>
              <a href="{{ route('social.requests') }}"><x-mx.icon name="briefcase" :size="18"/> Mes candidatures et engagements</a>
              <a href="{{ route('social.events') }}"><x-mx.icon name="calendar" :size="18"/> Événements</a>
              <a href="{{ route('social.settings') }}"><x-mx.icon name="settings" :size="18"/> Paramètres</a>
              <form method="POST" action="{{ route('social.logout') }}">@csrf<button class="is-danger"><x-mx.icon name="logout" :size="18"/> Déconnexion</button></form>
            </div>
          </details>
        @else
          <a class="mx-btn mx-btn--ghost mx-btn--sm mx-hide-sm" href="{{ route('social.login') }}">Connexion</a>
          <a class="mx-btn mx-btn--primary mx-btn--sm mx-hide-m" href="{{ route('social.register') }}">Inscription</a>
        @endif
        {{-- Menu complet (téléphone et tablette) : toutes les rubriques du réseau. --}}
        <details class="mx-menu mx-drawer mx-show-m">
          <summary class="mx-iconbtn" aria-label="Menu"><x-mx.icon name="menu" :size="20" /></summary>
          <div class="mx-menu__list mx-drawer__panel">
            <div class="mx-drawer__top"><b>Menu</b><button type="button" class="mx-iconbtn" data-close-drawer aria-label="Fermer le menu"><x-mx.icon name="x" :size="20"/></button></div>
            @if ($viewer)
              <a class="mx-drawer__me" href="{{ route('social.user', $viewer) }}"><x-mx.avatar :src="$viewer->avatarUrl()" :name="$viewer->name" /><span><b>{{ $viewer->name }}</b><span class="mx-xs mx-muted">Voir mon profil</span></span></a>
            @else
              <div class="mx-row" style="padding:6px;gap:8px">
                <a class="mx-btn mx-btn--primary mx-btn--sm" style="flex:1" href="{{ route('social.register') }}">Créer un compte</a>
                <a class="mx-btn mx-btn--ghost mx-btn--sm" style="flex:1" href="{{ route('social.login') }}">Connexion</a>
              </div>
            @endif
            <div class="mx-drawer__grid">
              @foreach ($nav as [$key, $route, $icon, $label, $needs])
                <a href="{{ route($route) }}" @class(['is-active' => $active === $key])><x-mx.icon :name="$icon" :size="20"/> {{ $label }}</a>
              @endforeach
              @if ($viewer)
                @foreach ($mine as [$key, $route, $icon, $label])
                  <a href="{{ route($route) }}" @class(['is-active' => $active === $key])><x-mx.icon :name="$icon" :size="20"/> {{ $label }}</a>
                @endforeach
                <a href="{{ route('social.settings') }}" @class(['is-active' => $active === 'parametres'])><x-mx.icon name="settings" :size="20"/> Paramètres</a>
              @endif
              <a href="{{ route('social.about') }}"><x-mx.icon name="shield" :size="20"/> À propos et règles</a>
            </div>
            <p class="mx-nav__title">Domaines</p>
            <div class="mx-drawer__grid">
              @foreach (\App\Support\SocialDomains::ALL as $code => $label)
                <a href="{{ route('social.discover', ['domaine' => $code]) }}"><x-mx.icon :name="'d-' . $code" :size="20"/> {{ $label }}</a>
              @endforeach
            </div>
            @if ($viewer)
              <form method="POST" action="{{ route('social.logout') }}">@csrf<button class="is-danger"><x-mx.icon name="logout" :size="18"/> Déconnexion</button></form>
            @endif
          </div>
        </details>
      </div>
    </div>
  </header>
  {{-- Barre d'icônes (téléphone et tablette) --}}
  <nav class="mx-iconnav" aria-label="Navigation rapide">
    <a href="{{ route('social.home') }}" @class(['is-active' => $active === 'fil']) aria-label="Accueil" @if($active === 'fil') aria-current="page" @endif><x-mx.icon name="home" :size="24"/></a>
    @if ($viewer)
      <a href="{{ route('social.connections') }}" @class(['is-active' => in_array($active, ['connexions', 'abonnements'], true)]) aria-label="Mes connexions et abonnements"><x-mx.icon name="users" :size="24"/></a>
      <a href="{{ route('social.messages') }}" @class(['is-active' => $active === 'messages']) aria-label="Messages{{ $unreadMsg ? ' ('.$unreadMsg.' non lus)' : '' }}"><x-mx.icon name="message" :size="24"/>@if($unreadMsg)<span class="mx-count">{{ $unreadMsg > 9 ? '9+' : $unreadMsg }}</span>@endif</a>
    @else
      <a href="{{ route('social.discover') }}" @class(['is-active' => $active === 'decouvrir']) aria-label="Découvrir"><x-mx.icon name="compass" :size="24"/></a>
    @endif
    <a href="{{ route('social.reels') }}" @class(['is-active' => $active === 'reels']) aria-label="Réels"><x-mx.icon name="reel" :size="24"/></a>
    <a href="{{ route('social.groups') }}" @class(['is-active' => $active === 'groupes']) aria-label="Groupes"><x-mx.icon name="groups" :size="24"/></a>
    @if ($viewer)
      <a href="{{ route('social.notifications') }}" @class(['is-active' => $active === 'notifications']) aria-label="Notifications{{ $unreadNotif ? ' ('.$unreadNotif.' non lues)' : '' }}"><x-mx.icon name="bell" :size="24"/>@if($unreadNotif)<span class="mx-count">{{ $unreadNotif > 9 ? '9+' : $unreadNotif }}</span>@endif</a>
    @else
      <a href="{{ route('social.events') }}" @class(['is-active' => $active === 'evenements']) aria-label="Événements"><x-mx.icon name="calendar" :size="24"/></a>
    @endif
    <a href="{{ route('social.mutuals') }}" @class(['is-active' => in_array($active, ['mutuelles', 'organisations'], true)]) aria-label="Organisations"><x-mx.icon name="building" :size="24"/></a>
  </nav>

  @if ($bare)
    {{ $slot }}
  @else
  <div @class(['mx-shell', 'mx-shell--wide' => $wide])>
    <aside class="mx-left" aria-label="Navigation principale">
      <div class="mx-sticky mx-stack">
        <nav class="mx-nav" aria-label="Rubriques">
          @foreach ($nav as [$key, $route, $icon, $label, $needs])
            <a href="{{ route($route) }}" @class(['is-active' => $active === $key])><x-mx.icon :name="$icon" /> {{ $label }}</a>
          @endforeach
          <p class="mx-nav__title">Domaines</p>
          @foreach (\App\Support\SocialDomains::ALL as $code => $label)
            <a class="mx-nav__dom" href="{{ route('social.discover', ['domaine' => $code]) }}" @class(['is-active' => $active === 'decouvrir' && request('domaine') === $code])><x-mx.icon :name="'d-' . $code" :size="18" /> {{ $label }}</a>
          @endforeach
          @if ($viewer)
            <p class="mx-nav__title">Mes espaces</p>
            <a href="{{ route('social.messages') }}" @class(['is-active' => $active === 'messages'])><x-mx.icon name="message" /> Messages @if($unreadMsg)<span class="mx-badge mx-badge--blue" style="margin-left:auto">{{ $unreadMsg }}</span>@endif</a>
            <a href="{{ route('social.notifications') }}" @class(['is-active' => $active === 'notifications'])><x-mx.icon name="bell" /> Notifications @if($unreadNotif)<span class="mx-badge mx-badge--blue" style="margin-left:auto">{{ $unreadNotif }}</span>@endif</a>
            @foreach ($mine as [$key, $route, $icon, $label])
              <a href="{{ route($route) }}" @class(['is-active' => $active === $key])><x-mx.icon :name="$icon" /> {{ $label }}</a>
            @endforeach
            <a href="{{ route('social.user', $viewer) }}" @class(['is-active' => $active === 'profil'])><x-mx.icon name="user" /> Mon profil</a>
            <a href="{{ route('social.settings') }}" @class(['is-active' => $active === 'parametres'])><x-mx.icon name="settings" /> Paramètres</a>
          @endif
        </nav>
        @if ($viewer)
          <a class="mx-btn mx-btn--primary mx-btn--lg mx-btn--block" href="{{ route('social.home') }}#composer"><x-mx.icon name="plus" /> Créer une publication</a>
        @else
          <div class="mx-card mx-card__pad mx-stack">
            <b>Rejoignez MX-CONNECT</b>
            <p class="mx-small mx-muted">Suivez les mutuelles, réagissez et publiez.</p>
            <a class="mx-btn mx-btn--primary mx-btn--block" href="{{ route('social.register') }}">Créer un compte</a>
          </div>
        @endif
      </div>
    </aside>

    <main class="mx-main" id="contenu">
      @foreach (['ok' => 'ok', 'error' => 'err', 'info' => 'info'] as $key => $cls)
        @if (session($key))<div class="mx-alert mx-alert--{{ $cls }}" role="status">{{ session($key) }}</div>@endif
      @endforeach
      @if ($legalPending)<div class="mx-alert mx-alert--info" role="status">Nos documents ont été mis à jour. <a href="{{ route('social.legal.pending', ['suite' => '/' . ltrim(request()->path(), '/')]) }}"><b>Les consulter et les valider</b></a> pour continuer à publier et à réagir.</div>@endif
      @if ($light)<div class="mx-alert mx-alert--info" role="status">Le réseau est très sollicité : affichage allégé pendant quelques minutes (moins de publications par page, vidéos sans lecture automatique). Tout reste accessible.</div>@endif
      {{ $slot }}
    </main>

    @unless ($wide)
      <aside class="mx-right" aria-label="Suggestions">
        <div class="mx-sticky mx-stack">
          @if (isset($right)) {{ $right }} @else @include('social.partials.sidebar') @endif
        </div>
      </aside>
    @endunless
  </div>
  @endif


  {{-- Barre du bas (mode application uniquement) : maquettes 7 à 14 --}}
  <nav class="mx-tabbar" aria-label="Navigation de l’application">
    <a href="{{ route('social.home') }}" @class(['is-active' => $active === 'fil']) @if($active === 'fil') aria-current="page" @endif><x-mx.icon name="home" :size="24"/>Accueil</a>
    <a href="{{ route('social.discover') }}" @class(['is-active' => $active === 'decouvrir'])><x-mx.icon name="compass" :size="24"/>Découvrir</a>
    <a href="{{ $viewer ? route('social.home') . '#composer' : route('social.app.auth') }}" aria-label="Publier"><span class="mx-tabbar__plus"><x-mx.icon name="plus" :size="26"/></span>Publier</a>
    <a href="{{ $viewer ? route('social.messages') : route('social.app.auth') }}" @class(['is-active' => $active === 'messages'])><x-mx.icon name="message" :size="24"/>@if($unreadMsg)<span class="mx-count">{{ $unreadMsg > 9 ? '9+' : $unreadMsg }}</span>@endif Messages</a>
    <a href="{{ $viewer ? route('social.app.menu') : route('social.app.auth') }}" @class(['is-active' => in_array($active, ['profil', 'parametres', 'menu'], true)])><x-mx.icon name="user" :size="24"/>Profil</a>
  </nav>

  <div class="mx-toasts" id="mx-toasts" aria-live="polite"></div>
  @include('social.partials.report-modal')
  <script src="/js/mx-social.js?v={{ config('mxconnect.asset_version') }}" defer></script>
  <script src="/js/mx-social-plus.js?v={{ config('mxconnect.asset_version') }}" defer></script>
  {{ $scripts ?? '' }}
</body>
</html>
