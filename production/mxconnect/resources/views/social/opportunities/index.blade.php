@php $O = \App\Models\Social\Opportunity::class; $filtered = $q || $category || $contract || $workplace || $region || $all; @endphp
<x-layouts.social title="Emplois et opportunités" active="opportunites">
  <section class="mx-card mx-card__pad mx-stack">
    <div>
      <h1 style="font-size:22px">Emplois et opportunités</h1>
      <p class="mx-small mx-muted">Offres d’emploi, stages, bénévolat, consultances, appels d’offres et appels à partenaires publiés par les mutuelles et les organisations du réseau.</p>
    </div>
    <div class="mx-chips" aria-label="Catégories">
      <a href="{{ route('social.opportunities', array_filter(['q' => $q, 'region' => $region])) }}" @class(['mx-chip', 'is-active' => ! $category])>Toutes</a>
      <a href="{{ route('social.opportunities', array_filter(['categorie' => 'emplois', 'q' => $q, 'region' => $region])) }}" @class(['mx-chip', 'is-active' => $category === 'emplois'])><x-mx.icon name="briefcase" :size="14"/> Emplois, stages, consultances</a>
      @foreach ($O::CATEGORIES as $k => $v)
        @unless (in_array($k, $O::JOB_CATEGORIES, true))
          <a href="{{ route('social.opportunities', array_filter(['categorie' => $k, 'q' => $q, 'region' => $region])) }}" @class(['mx-chip', 'is-active' => $category === $k])>{{ $v }}</a>
        @endunless
      @endforeach
    </div>
    <form method="GET" action="{{ route('social.opportunities') }}" class="mx-filters" role="search">
      @if ($category)<input type="hidden" name="categorie" value="{{ $category }}">@endif
      <label class="mx-sr" for="op-q">Mots-clés</label>
      <input class="mx-input" id="op-q" name="q" value="{{ $q }}" placeholder="Poste, compétence, ville, organisation…" maxlength="100">
      <label class="mx-sr" for="op-contrat">Contrat</label>
      <select class="mx-select" id="op-contrat" name="contrat"><option value="">Tous les contrats</option>@foreach ($O::CONTRACTS as $k => $v)<option value="{{ $k }}" @selected($contract === $k)>{{ $v }}</option>@endforeach</select>
      <label class="mx-sr" for="op-lieu">Lieu de travail</label>
      <select class="mx-select" id="op-lieu" name="lieu"><option value="">Sur site ou à distance</option>@foreach ($O::WORKPLACES as $k => $v)<option value="{{ $k }}" @selected($workplace === $k)>{{ $v }}</option>@endforeach</select>
      <label class="mx-sr" for="op-region">Région</label>
      <select class="mx-select" id="op-region" name="region"><option value="">Toutes les régions</option>@foreach ($regions as $r)<option value="{{ $r }}" @selected($region === $r)>{{ $r }}</option>@endforeach</select>
      <label class="mx-check mx-small" style="grid-column:1/-1"><input type="checkbox" name="toutes" value="1" @checked($all)> <span>Afficher aussi les offres pourvues, clôturées ou dont la date limite est passée</span></label>
      <div class="mx-row mx-row--wrap" style="grid-column:1/-1"><button class="mx-btn mx-btn--primary mx-btn--sm"><x-mx.icon name="search" :size="16"/> Rechercher</button>
        @if ($filtered)<a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.opportunities') }}">Effacer les filtres</a>@endif</div>
    </form>
  </section>

  <p class="mx-small mx-muted" style="padding:0 6px">{{ $items->total() }} {{ $items->total() > 1 ? 'opportunités' : 'opportunité' }}{{ $all ? '' : ' ouverte' . ($items->total() > 1 ? 's' : '') }}</p>
  @forelse ($items as $o)
    @include('social.opportunities._card', ['o' => $o])
  @empty
    @include('social.partials.empty', ['icon' => 'briefcase', 'title' => 'Aucune opportunité trouvée', 'text' => $filtered ? 'Essayez d’autres mots-clés ou filtres.' : 'Les offres publiées par les mutuelles et les organisations apparaîtront ici.'])
  @endforelse
  {{ $items->links('social.partials.pagination') }}
  <p class="mx-xs mx-muted" style="padding:0 6px">MX-CONNECT ne prélève jamais d’argent pour une candidature. Méfiez-vous de toute offre qui vous demande un paiement, et signalez-la.</p>
</x-layouts.social>
