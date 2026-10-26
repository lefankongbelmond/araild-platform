@php $tabs = ['tout' => 'Tout', 'mutuelles' => 'Mutuelles', 'publications' => 'Publications', 'membres' => 'Membres']; @endphp
<x-layouts.social title="Recherche">
  <section class="mx-card">
    <form method="GET" action="{{ route('social.search') }}" class="mx-row" style="padding:16px">
      <label class="mx-sr" for="s-q">Rechercher</label>
      <input id="s-q" class="mx-input" name="q" value="{{ $q }}" minlength="2" maxlength="100" placeholder="Nom de mutuelle, ville, mot-clé…" autofocus>
      @if ($tab !== 'tout')<input type="hidden" name="onglet" value="{{ $tab }}">@endif
      <button class="mx-btn mx-btn--primary">Rechercher</button>
    </form>
    <nav class="mx-tabs" aria-label="Type de résultat">
      @foreach ($tabs as $k => $v)
        <a href="{{ route('social.search', array_filter(['q' => $q, 'onglet' => $k === 'tout' ? null : $k])) }}" @class(['is-active' => $tab === $k])>{{ $v }}</a>
      @endforeach
    </nav>
  </section>

  @if (mb_strlen($q) < 2)
    @include('social.partials.empty', ['icon' => 'search', 'title' => 'Que recherchez-vous ?', 'text' => 'Saisissez au moins 2 caractères : nom d’une mutuelle, ville, région ou mot-clé.'])
  @else
    @if (in_array($tab, ['tout', 'mutuelles']))
      <section class="mx-card">
        <div class="mx-card__head"><h2 class="mx-card__title">Mutuelles</h2>@if($tab === 'tout' && $mutuals->count() >= 5)<a class="mx-small" href="{{ route('social.search', ['q' => $q, 'onglet' => 'mutuelles']) }}">Voir tout</a>@endif</div>
        <div class="mx-mini">
          @forelse ($mutuals as $m) @include('social.partials.mutual-mini', ['m' => $m]) @empty <p class="mx-mini__item mx-small mx-muted">Aucune mutuelle trouvée.</p> @endforelse
        </div>
      </section>
    @endif
    @if (in_array($tab, ['tout', 'membres']))
      <section class="mx-card">
        <div class="mx-card__head"><h2 class="mx-card__title">Membres</h2>@if($tab === 'tout' && $members->count() >= 5)<a class="mx-small" href="{{ route('social.search', ['q' => $q, 'onglet' => 'membres']) }}">Voir tout</a>@endif</div>
        <div class="mx-mini">
          @forelse ($members as $a)
            <a class="mx-mini__item" href="{{ route('social.user', $a) }}" style="color:inherit">
              <x-mx.avatar :src="$a->avatarUrl()" :name="$a->name" />
              <span class="mx-grow"><b>{{ $a->name }}</b><span class="mx-xs mx-muted">{{ $a->city ?: 'Membre MX-CONNECT' }}</span></span>
            </a>
          @empty <p class="mx-mini__item mx-small mx-muted">Aucun membre trouvé (seuls les profils publics apparaissent).</p> @endforelse
        </div>
      </section>
    @endif
    @if (in_array($tab, ['tout', 'publications']) && $posts)
      <h2 style="font-size:18px">Publications</h2>
      @if ($posts->isEmpty())
        <p class="mx-card mx-card__pad mx-small mx-muted">Aucune publication ne contient « {{ $q }} ».</p>
      @else
        <div class="mx-stack">@include('social.partials.feed', ['posts' => $posts, 'state' => $state])</div>
      @endif
    @endif
  @endif
</x-layouts.social>
