<x-layouts.social title="Projets et résultats" active="projets">
  <section class="mx-card mx-card__pad mx-stack">
    <div>
      <h1 style="font-size:22px">Projets et résultats</h1>
      <p class="mx-small mx-muted">Les projets publiés par les mutuelles et les organisations du réseau : objectifs, résultats et besoins. Engagez-vous ou devenez partenaire.</p>
    </div>
    <form method="GET" action="{{ route('social.projects') }}" class="mx-filters" role="search">
      <label class="mx-sr" for="pj-q">Mots-clés</label>
      <input class="mx-input" id="pj-q" name="q" value="{{ $q }}" placeholder="Mot-clé, zone…" maxlength="100">
      <label class="mx-sr" for="pj-etat">État</label>
      <select class="mx-select" id="pj-etat" name="etat"><option value="">Tous les états</option>@foreach (\App\Models\Social\Project::PHASES as $k => $v)<option value="{{ $k }}" @selected($phase === $k)>{{ $v }}</option>@endforeach</select>
      <label class="mx-sr" for="pj-besoin">Besoin</label>
      <select class="mx-select" id="pj-besoin" name="besoin"><option value="">Tous les besoins</option>@foreach (\App\Http\Controllers\Social\ProjectController::NEEDS as $k => $v)<option value="{{ $k }}" @selected($need === $k)>{{ $v }}</option>@endforeach</select>
      <label class="mx-sr" for="pj-region">Région</label>
      <select class="mx-select" id="pj-region" name="region"><option value="">Toutes les régions</option>@foreach ($regions as $r)<option value="{{ $r }}" @selected($region === $r)>{{ $r }}</option>@endforeach</select>
      <div class="mx-row mx-row--wrap" style="grid-column:1/-1"><button class="mx-btn mx-btn--primary mx-btn--sm"><x-mx.icon name="search" :size="16"/> Rechercher</button>
        @if ($q || $phase || $need || $region)<a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.projects') }}">Effacer les filtres</a>@endif</div>
    </form>
  </section>

  <p class="mx-xs mx-muted" style="padding:0 6px"><span class="mx-badge mx-badge--declared">Déclaré</span> chiffre publié par l’organisation · <span class="mx-badge mx-badge--verified">Vérifié</span> chiffre contrôlé par l’administration du réseau.</p>

  @if ($projects->isNotEmpty())
    <div class="mx-cards" style="grid-template-columns:repeat(auto-fill,minmax(230px,1fr))">@foreach ($projects as $p)@include('social.partials.project-tile', ['p' => $p])@endforeach</div>
  @else
    @include('social.partials.empty', ['icon' => 'target', 'title' => 'Aucun projet trouvé', 'text' => $q || $phase || $need || $region ? 'Essayez d’autres filtres.' : 'Les projets publiés par les mutuelles et les organisations apparaîtront ici.'])
  @endif
  {{ $projects->links('social.partials.pagination') }}
</x-layouts.social>
