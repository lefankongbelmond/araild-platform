@php
  $T = \App\Http\Controllers\Social\DiscoverController::TABS;
  $D = \App\Support\SocialDomains::ALL;
  $keep = fn (array $over = []) => array_filter(array_merge(['onglet' => $tab === 'organisations' ? null : $tab, 'q' => $f['q'], 'domaine' => $f['domaine'], 'pays' => $f['pays'], 'region' => $f['region']], $over));
  $types = match ($tab) {
      'projets' => \App\Models\Social\Project::PHASES,
      'opportunites' => \App\Models\Social\Opportunity::CATEGORIES,
      'organisations' => \App\Models\Social\MutualProfile::CATEGORIES,
      default => [],
  };
  $filtered = $f['q'] || $f['domaine'] || $f['pays'] || $f['region'] || $f['type'];
@endphp
<x-layouts.social title="Découvrir" active="decouvrir" wide>
  <section class="mx-banner">
    <h1>Découvrir</h1>
    <p style="margin-top:8px;max-width:560px">Des organisations, des initiatives et des opportunités pour des communautés plus fortes.</p>
    <form class="mx-bigsearch" method="GET" action="{{ route('social.discover') }}" role="search">
      @foreach (['onglet' => $tab === 'organisations' ? null : $tab, 'domaine' => $f['domaine']] as $k => $v)@if($v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
      <label class="mx-sr" for="dc-q">Rechercher</label>
      <input id="dc-q" name="q" value="{{ $f['q'] }}" maxlength="100" placeholder="Rechercher une organisation, un projet, un service…">
      <button class="mx-btn mx-btn--primary"><x-mx.icon name="search" :size="16"/> <span class="mx-hide-sm">Rechercher</span></button>
    </form>
  </section>

  <div class="mx-chips" aria-label="Domaines">
    @foreach ($D as $code => $label)
      <a href="{{ route('social.discover', $keep(['domaine' => $f['domaine'] === $code ? null : $code])) }}" @class(['mx-chip', 'is-active' => $f['domaine'] === $code])><x-mx.icon :name="'d-' . $code" :size="15"/> {{ $label }}</a>
    @endforeach
  </div>

  <nav class="mx-underline-tabs" aria-label="Type de résultats">
    @foreach ($T as $k => $v)
      <a href="{{ route('social.discover', array_filter(['onglet' => $k === 'organisations' ? null : $k, 'q' => $f['q'], 'domaine' => $f['domaine'], 'pays' => $f['pays'], 'region' => $f['region']])) }}" @class(['is-active' => $tab === $k])>{{ $v }}</a>
    @endforeach
  </nav>

  <div class="mx-discover">
    <aside class="mx-stack">
      <form class="mx-card mx-card__pad mx-stack" method="GET" action="{{ route('social.discover') }}" style="gap:10px">
        <b class="mx-row" style="gap:6px"><x-mx.icon name="filter" :size="16"/> Filtres</b>
        @if ($tab !== 'organisations')<input type="hidden" name="onglet" value="{{ $tab }}">@endif
        @if ($f['q'])<input type="hidden" name="q" value="{{ $f['q'] }}">@endif
        <div class="mx-field"><label class="mx-label" for="dc-pays">Pays</label>
          <select class="mx-select" id="dc-pays" name="pays"><option value="">Tous les pays</option>@foreach ($countries as $c)<option value="{{ $c->id }}" @selected($f['pays'] === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="mx-field"><label class="mx-label" for="dc-region">Région</label>
          <select class="mx-select" id="dc-region" name="region"><option value="">Toutes les régions</option>@foreach ($places['regions'] as $r)<option value="{{ $r }}" @selected($f['region'] === $r)>{{ $r }}</option>@endforeach</select></div>
        @if ($types)
          <div class="mx-field"><label class="mx-label" for="dc-type">{{ $tab === 'projets' ? 'État du projet' : ($tab === 'opportunites' ? 'Type d’opportunité' : 'Type de structure') }}</label>
            <select class="mx-select" id="dc-type" name="type"><option value="">Tous</option>@foreach ($types as $k => $v)<option value="{{ $k }}" @selected($f['type'] === $k)>{{ $v }}</option>@endforeach</select></div>
        @endif
        <div class="mx-field"><label class="mx-label" for="dc-dom">Domaine d’intervention</label>
          <select class="mx-select" id="dc-dom" name="domaine"><option value="">Tous les domaines</option>@foreach ($D as $k => $v)<option value="{{ $k }}" @selected($f['domaine'] === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="mx-row mx-row--wrap" style="gap:8px"><button class="mx-btn mx-btn--primary mx-btn--sm">Rechercher</button>
          @if ($filtered)<a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.discover', array_filter(['onglet' => $tab === 'organisations' ? null : $tab])) }}">Réinitialiser</a>@endif</div>
      </form>
      <section class="mx-card mx-card__pad mx-stack" style="gap:10px">
        <b>Chiffres clés</b>
        <div class="mx-statgrid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
          <div><b>{{ $stats['organisations'] }}</b><span>Organisations</span></div>
          <div><b>{{ $stats['projets'] }}</b><span>Projets</span></div>
          <div><b>{{ $stats['evenements'] }}</b><span>Événements à venir</span></div>
          <div><b>{{ $stats['opportunites'] }}</b><span>Offres ouvertes</span></div>
        </div>
        @if ($regions->isNotEmpty())
          <b style="margin-top:4px">Régions les plus actives</b>
          <div class="mx-regions">
            @php $max = max(1, $regions->max()); @endphp
            @foreach ($regions as $r => $n)
              <a href="{{ route('social.discover', $keep(['region' => $r])) }}" style="color:inherit"><div><span>{{ $r }}</span><span>{{ $n }}</span></div><span class="mx-bar"><i style="width:{{ round($n / $max * 100) }}%"></i></span></a>
            @endforeach
          </div>
        @endif
      </section>
    </aside>

    <section class="mx-stack" aria-live="polite">
      <p class="mx-small mx-muted">{{ $items->total() }} résultat(s)@if($f['domaine']) · {{ $D[$f['domaine']] }}@endif</p>
      @if ($items->isEmpty())
        @include('social.partials.empty', ['icon' => 'compass', 'title' => 'Aucun résultat', 'text' => $filtered ? 'Essayez un autre domaine, une autre région ou d’autres mots-clés.' : 'Rien à afficher dans cette rubrique pour le moment.'])
      @elseif ($tab === 'organisations')
        <div class="mx-cards">@foreach ($items as $m)@include('social.partials.org-card', ['m' => $m])@endforeach</div>
      @elseif ($tab === 'projets')
        <div class="mx-cards">@foreach ($items as $p)@include('social.partials.project-tile', ['p' => $p])@endforeach</div>
      @elseif ($tab === 'opportunites')
        @foreach ($items as $o)@include('social.opportunities._card', ['o' => $o])@endforeach
      @else
        @foreach ($items as $e)
          <a class="mx-card mx-listcard" href="{{ route('social.post', $e) }}">
            <span class="mx-cal" style="background:var(--mx-blue-soft);border-radius:12px;padding:6px 10px;color:var(--mx-blue-2)"><b>{{ $e->event_starts_at->format('d') }}</b><span>{{ $e->event_starts_at->translatedFormat('M') }}</span></span>
            <span class="mx-listcard__body">
              <span class="mx-listcard__title">{{ \Illuminate\Support\Str::limit($e->body, 110) }}</span>
              <span class="mx-meta"><span><x-mx.icon name="clock" :size="13"/> {{ $e->event_starts_at->translatedFormat('l j F Y, H\hi') }}</span>
                @if ($e->event_location)<span><x-mx.icon name="pin" :size="13"/> {{ $e->event_location }}</span>@endif
                <span><x-mx.icon name="building" :size="13"/> {{ $e->authorName() }}</span></span>
            </span>
          </a>
        @endforeach
      @endif
      {{ $items->links('social.partials.pagination') }}
    </section>
  </div>
</x-layouts.social>
