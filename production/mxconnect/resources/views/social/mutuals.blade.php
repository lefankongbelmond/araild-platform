@php
  $pageTitle = match ($category) { 'mutuelle' => 'Mutuelles de santé', '' => 'Organisations et mutuelles', default => \App\Models\Social\MutualProfile::CATEGORIES[$category] ?? 'Organisations' };
  if ($category === 'organisations') $pageTitle = 'Organisations';
@endphp
<x-layouts.social :title="$pageTitle" active="organisations">
  @include('social.partials.feedtabs', ['current' => in_array($category, ['organisations', 'mutuelle'], true) ? ($category === 'mutuelle' ? 'mutuelles' : 'organisations') : null])
  <section class="mx-card">
    <div class="mx-card__head"><h1 class="mx-card__title" style="font-size:20px">{{ $pageTitle }}</h1><span class="mx-small mx-muted">{{ $mutuals->total() }} résultat(s)</span></div>
    <form method="GET" action="{{ route('social.mutuals') }}" class="mx-row mx-row--wrap" style="padding:0 16px 16px">
      <label class="mx-sr" for="m-q">Nom ou ville</label>
      <input id="m-q" class="mx-input" name="q" value="{{ $q }}" placeholder="Nom ou ville" style="flex:2;min-width:180px">
      <label class="mx-sr" for="m-cat">Type de structure</label>
      <select id="m-cat" class="mx-select" name="categorie" style="flex:1;min-width:150px">
        <option value="">Tous les types de structure</option>
        <option value="organisations" @selected($category === 'organisations')>Toutes les organisations (hors mutuelles)</option>
        @foreach (\App\Models\Social\MutualProfile::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected($category === $k)>{{ $v }}</option>@endforeach
      </select>
      <label class="mx-sr" for="m-reg">Région</label>
      <select id="m-reg" class="mx-select" name="region" style="flex:1;min-width:140px">
        <option value="">Toutes régions</option>
        @foreach ($places['regions'] as $r)<option @selected($region === $r)>{{ $r }}</option>@endforeach
      </select>
      <label class="mx-sr" for="m-city">Ville</label>
      <select id="m-city" class="mx-select" name="ville" style="flex:1;min-width:140px">
        <option value="">Toutes villes</option>
        @foreach ($places['cities'] as $c)<option @selected($city === $c)>{{ $c }}</option>@endforeach
      </select>
      <button class="mx-btn mx-btn--primary"><x-mx.icon name="search" :size="16"/> Rechercher</button>
    </form>
  </section>

  @if ($mutuals->isEmpty())
    @include('social.partials.empty', ['icon' => 'building', 'title' => 'Aucune structure trouvée', 'text' => 'Essayez un autre nom, une autre ville ou retirez les filtres.', 'action' => ['Voir tout l’annuaire', route('social.mutuals')]])
  @else
    <div class="mx-grid-2">
      @foreach ($mutuals as $m)
        @php $p = $m->profile; $following = isset($followMap[$m->id]); @endphp
        <article class="mx-card" style="overflow:hidden">
          <a href="{{ route('social.mutual', $m->slug) }}" aria-hidden="true" tabindex="-1">
            <div @class(['mx-cover', 'has-img' => $p?->cover_path]) style="height:90px;border-radius:0;@if($p?->cover_path)background-image:url('{{ $p->coverUrl() }}')@endif"></div>
          </a>
          <div class="mx-card__pad mx-stack" style="gap:10px;margin-top:-40px;position:relative;z-index:2">
            <x-mx.avatar :src="$p?->logoUrl()" :name="$m->name" org style="width:64px;height:64px;border:3px solid var(--mx-surface)" />
            <div>
              <a href="{{ route('social.mutual', $m->slug) }}" style="color:inherit"><b style="font-size:16px">{{ $m->name }}</b></a>
              @if($m->isVerified())<span class="mx-verified" title="Organisation vérifiée par l’administration du réseau"><x-mx.icon name="verified" :size="16"/></span>@endif
              @if($p?->is_demo)<span class="mx-demo-ribbon">Démo</span>@endif
              <div class="mx-row" style="gap:6px;flex-wrap:wrap;margin-top:4px"><span class="mx-orgtype">{{ \App\Models\Social\MutualProfile::CATEGORIES[$p->category ?? 'mutuelle'] ?? 'Mutuelle de santé' }}</span><span class="mx-xs mx-muted">{{ collect([$m->city, $m->region, $m->country?->name])->filter()->implode(', ') }}</span></div>
            </div>
            @if ($p?->tagline)<p class="mx-small">{{ $p->tagline }}</p>@endif
            <div class="mx-row mx-between">
              <span class="mx-xs mx-muted"><b data-followers="{{ route('social.follow', $m->slug) }}">{{ number_format($p->followers_count ?? 0, 0, ',', ' ') }}</b> abonné(s)</span>
              <span class="mx-row">
                <a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.mutual', $m->slug) }}">Voir la page</a>
                @auth('member')
                  <button class="mx-btn mx-btn--soft mx-btn--sm" data-follow="{{ route('social.follow', $m->slug) }}" @class(['is-on' => $following])>{{ $following ? 'Suivi' : 'Suivre' }}</button>
                @else
                  <a class="mx-btn mx-btn--soft mx-btn--sm" href="{{ route('social.login') }}">Suivre</a>
                @endauth
              </span>
            </div>
          </div>
        </article>
      @endforeach
    </div>
    {{ $mutuals->onEachSide(1)->links('social.partials.pagination') }}
  @endif
</x-layouts.social>
