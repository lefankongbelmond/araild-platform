@php $current = $current ?? 'accueil'; $home = route('home'); @endphp
<header class="h-header" id="haut">
  <div class="h-wrap h-header__in">
    <a class="h-logo" href="{{ $home }}" aria-label="MX-CONNECT — accueil">
      <img src="/brand/mx-mark-128.webp" alt="" width="58" height="58">
      <span><span class="h-logo__name">M<b>X</b>-CONNECT</span><span class="h-logo__tag">CONNECTER • COLLABORER • RÉUSSIR</span></span>
    </a>
    <nav class="h-nav" aria-label="Navigation principale" id="h-nav">
      <a href="{{ $home }}" @class(['is-active' => $current === 'accueil'])>Accueil</a>
      <a href="{{ $home }}#apropos">À propos</a>
      <a href="{{ $home }}#fonctionnalites">Fonctionnalités</a>
      <details class="h-drop">
        <summary>Solutions</summary>
        <div class="h-drop__menu">
          <a href="{{ $home }}#solutions">Mutuelles de santé<small>Gestion complète, de l’adhésion aux prestations</small></a>
          <a href="{{ $home }}#solutions">Réseaux et faîtières<small>Consolidation, facturation, supervision</small></a>
          <a href="{{ $home }}#solutions">Adhérents<small>Espace adhérent, carte digitale, réseau social</small></a>
        </div>
      </details>
      <a href="{{ $home }}#tarifs">Tarifs</a>
      <a href="{{ route('social.home') }}">Actualités</a>
      <a href="{{ $home }}#contact">Contact</a>
      <div class="h-mobile-only" style="flex-direction:column">
        <a class="h-btn h-btn--ghost" href="{{ route('home.login') }}">Se connecter</a>
        <a class="h-btn h-btn--grad" href="{{ $home }}#contact">Demander une démo</a>
      </div>
    </nav>
    <div class="h-actions">
      <a class="h-login" href="{{ route('home.login') }}">Se connecter</a>
      <a class="h-btn h-btn--grad" href="{{ $home }}#contact">Demander une démo</a>
    </div>
    <button class="h-burger" type="button" aria-label="Ouvrir le menu" aria-controls="h-nav" aria-expanded="false"><span></span></button>
  </div>
</header>
