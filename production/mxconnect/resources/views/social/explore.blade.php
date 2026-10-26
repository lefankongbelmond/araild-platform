<x-layouts.social title="Explorer" active="explorer">
  @include('social.partials.feedtabs', ['current' => 'explorer'])
  <div class="mx-chips">
    <a class="mx-chip" href="{{ route('social.events') }}"><x-mx.icon name="calendar" :size="15"/> Événements</a>
    <a class="mx-chip" href="{{ route('social.mutuals') }}"><x-mx.icon name="building" :size="15"/> Annuaire</a>
    <a class="mx-chip" href="{{ route('social.groups') }}"><x-mx.icon name="users" :size="15"/> Groupes</a>
    <a class="mx-chip" href="{{ route('social.all', ['type' => 'campagne']) }}"><x-mx.icon name="megaphone" :size="15"/> Campagnes</a>
    <a class="mx-chip" href="{{ route('comparator') }}"><x-mx.icon name="chart" :size="15"/> Comparer les offres</a>
  </div>

  <section class="mx-card">
    <div class="mx-card__head"><h1 class="mx-card__title">Mutuelles et organisations</h1><a class="mx-small" href="{{ route('social.mutuals') }}">Annuaire complet</a></div>
    <form method="GET" class="mx-row mx-row--wrap" style="padding:0 16px 12px">
      <label class="mx-sr" for="e-cat">Catégorie</label>
      <select id="e-cat" class="mx-select" name="categorie" style="width:auto;flex:1">
        <option value="">Toutes les catégories</option>
        @foreach (\App\Models\Social\MutualProfile::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected($category === $k)>{{ $v }}</option>@endforeach
      </select>
      <label class="mx-sr" for="e-reg">Région</label>
      <select id="e-reg" class="mx-select" name="region" style="width:auto;flex:1">
        <option value="">Toutes les régions</option>
        @foreach ($places['regions'] as $r)<option @selected($region === $r)>{{ $r }}</option>@endforeach
      </select>
      <button class="mx-btn mx-btn--soft">Afficher</button>
    </form>
    <div class="mx-mini">
      @forelse ($mutuals as $m)
        @include('social.partials.mutual-mini', ['m' => $m])
      @empty
        <p class="mx-mini__item mx-small mx-muted">Aucune mutuelle ne correspond.</p>
      @endforelse
    </div>
  </section>

  <h2 style="font-size:18px;margin-top:6px">Publications populaires ce mois-ci</h2>
  @forelse ($popular as $post)
    @include('social.partials.post', ['post' => $post, 'state' => $state])
  @empty
    @include('social.partials.empty', ['icon' => 'chart', 'title' => 'Pas encore de tendance', 'text' => 'Les publications les plus commentées et partagées des 30 derniers jours apparaîtront ici.'])
  @endforelse

  <section class="mx-card mx-card__pad">
    <h2 class="mx-card__title" style="margin-bottom:10px">Thèmes</h2>
    <div class="mx-chips" style="flex-wrap:wrap">
      @foreach (\App\Models\Social\Post::ACTIVITY_CATEGORIES as $k => $v)
        <a class="mx-chip" href="{{ route('social.all', ['categorie' => $k]) }}">{{ $v }}</a>
      @endforeach
    </div>
  </section>
</x-layouts.social>
