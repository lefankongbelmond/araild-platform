{{-- Maquette 7 — application seulement : recherche et domaines en tête de l'accueil (la recherche
     quitte l'en-tête en mode application). Les domaines ouvrent « Découvrir » filtré. --}}
<div class="mx-apphome">
  <form class="mx-apphome__search" action="{{ route('social.search') }}" method="GET" role="search">
    <x-mx.icon name="search" :size="18" />
    <label class="mx-sr" for="mx-aq">Rechercher</label>
    <input id="mx-aq" name="q" placeholder="Rechercher organisations, projets, personnes…" autocomplete="off">
  </form>
  <div class="mx-chips" role="list" aria-label="Domaines">
    <a role="listitem" class="mx-chip is-active" href="{{ route('social.home') }}" aria-current="page">Tous</a>
    @foreach (\App\Support\SocialDomains::ALL as $code => $label)
      <a role="listitem" class="mx-chip" href="{{ route('social.discover', ['domaine' => $code]) }}"><x-mx.icon :name="'d-' . $code" :size="16"/> {{ $label }}</a>
    @endforeach
  </div>
</div>
