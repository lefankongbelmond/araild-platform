@php
  $viewer = auth('member')->user();
  $types = \App\Models\Social\Post::TYPES;
  $active = $tab === 'enregistrements' ? 'enregistrements' : ($tab === 'abonnements' ? 'abonnements' : ($tab === 'toutes' ? 'toutes' : 'fil'));
@endphp
<x-layouts.social :title="$title" :active="$active">
  @if (! in_array($tab, ['fil', 'abonnements', 'toutes'], true))
    <h1 style="font-size:22px">{{ $title }}</h1>
  @endif

  @if ($tab === 'fil')
    @include('social.partials.app-home-search')
  @endif

  @if (! empty($composer))
    @include('social.partials.composer')
    @guest('member')
      <section class="mx-card mx-card__pad mx-row mx-between mx-row--wrap mx-welcome">
        <div><b>Bienvenue sur MX-CONNECT</b><p class="mx-small mx-muted">Créez un compte gratuit pour suivre les mutuelles, réagir et publier.</p></div>
        <a class="mx-btn mx-btn--primary" href="{{ route('social.register') }}">Créer un compte</a>
      </section>
    @endguest
  @endif

  @if ($tab === 'fil')
    @include('social.partials.orgstrip')
  @endif

  @if (in_array($tab, ['fil', 'abonnements', 'toutes'], true))
    @include('social.partials.feedtabs', ['current' => $tab])
  @endif

  @if (! empty($filtersBar))
    <form class="mx-card mx-card__pad mx-stack" method="GET" action="{{ route('social.all') }}" aria-label="Filtrer les publications">
      <div class="mx-chips" role="list">
        <a role="listitem" href="{{ request()->fullUrlWithQuery(['type' => null, 'cursor' => null]) }}" @class(['mx-chip', 'is-active' => ! request('type')])>Tout</a>
        @foreach ($types as $k => $v)
          <a role="listitem" href="{{ request()->fullUrlWithQuery(['type' => $k, 'cursor' => null]) }}" @class(['mx-chip', 'is-active' => request('type') === $k])>{{ $v }}</a>
        @endforeach
      </div>
      <div class="mx-row mx-row--wrap">
        @if (request('type'))<input type="hidden" name="type" value="{{ request('type') }}">@endif
        <label class="mx-sr" for="f-cat">Thème</label>
        <select id="f-cat" class="mx-select" name="categorie" style="width:auto;flex:1;min-width:160px">
          <option value="">Tous les thèmes</option>
          @foreach (\App\Models\Social\Post::ACTIVITY_CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(request('categorie') === $k)>{{ $v }}</option>@endforeach
        </select>
        <label class="mx-sr" for="f-reg">Région</label>
        <select id="f-reg" class="mx-select" name="region" style="width:auto;flex:1;min-width:140px">
          <option value="">Toutes les régions</option>
          @foreach ($places['regions'] as $r)<option @selected(request('region') === $r)>{{ $r }}</option>@endforeach
        </select>
        <label class="mx-sr" for="f-city">Ville</label>
        <select id="f-city" class="mx-select" name="ville" style="width:auto;flex:1;min-width:140px">
          <option value="">Toutes les villes</option>
          @foreach ($places['cities'] as $c)<option @selected(request('ville') === $c)>{{ $c }}</option>@endforeach
        </select>
        <button class="mx-btn mx-btn--soft">Filtrer</button>
      </div>
    </form>
  @endif

  @if ($posts->isEmpty())
    @if ($tab === 'abonnements' && ($followCount ?? 0) === 0)
      @include('social.partials.empty', ['icon' => 'star', 'title' => 'Vous ne suivez encore aucune mutuelle', 'text' => 'Suivez des mutuelles pour voir ici leurs annonces, campagnes et événements.', 'action' => ['Découvrir les mutuelles', route('social.mutuals')]])
    @elseif ($tab === 'enregistrements')
      @include('social.partials.empty', ['icon' => 'bookmark', 'title' => 'Aucune publication enregistrée', 'text' => 'Utilisez « Enregistrer » sur une publication pour la retrouver ici.'])
    @elseif (request()->hasAny(['type', 'categorie', 'region', 'ville']))
      @include('social.partials.empty', ['icon' => 'search', 'title' => 'Aucun résultat', 'text' => 'Aucune publication ne correspond à ces filtres.', 'action' => ['Réinitialiser les filtres', route('social.all')]])
    @else
      @include('social.partials.empty', ['icon' => 'list', 'title' => 'Aucune publication pour le moment', 'text' => 'Les publications des mutuelles et des membres apparaîtront ici.', 'action' => ['Découvrir les mutuelles', route('social.mutuals')]])
    @endif
  @else
    <div class="mx-stack" aria-live="polite">
      @include('social.partials.feed')
    </div>
  @endif
</x-layouts.social>
