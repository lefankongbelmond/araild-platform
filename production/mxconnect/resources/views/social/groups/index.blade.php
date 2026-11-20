<x-layouts.social title="{{ __('Groupes') }}" active="groupes">
  <section class="mx-card">
    <div class="mx-card__head"><h1 class="mx-card__title" style="font-size:20px">{{ __('Groupes communautaires') }}</h1><span class="mx-small mx-muted">{{ $groups->total() }} groupe(s)</span></div>
    <p class="mx-small mx-muted" style="padding:0 16px">{{ __('Des espaces d’échange thématiques animés et modérés par les mutuelles. Ne partagez aucune information médicale personnelle.') }}</p>
    <form method="GET" class="mx-row" style="padding:12px 16px 16px">
      <label class="mx-sr" for="g-q">{{ __('Rechercher un groupe') }}</label>
      <input id="g-q" class="mx-input" name="q" value="{{ $q }}" placeholder="{{ __('Nom ou thème du groupe') }}">
      <button class="mx-btn mx-btn--soft">{{ __('Rechercher') }}</button>
    </form>
  </section>
  @if ($groups->isEmpty())
    @include('social.partials.empty', ['icon' => 'users', 'title' => 'Aucun groupe pour le moment', 'text' => 'Les mutuelles créent des groupes depuis leur espace de gestion. Ils apparaîtront ici.'])
  @else
    <div class="mx-grid-2">
      @foreach ($groups as $g)
        @php $st = $mine[$g->id] ?? null; @endphp
        <article class="mx-card" style="overflow:hidden">
          <a href="{{ route('social.group', $g) }}" aria-hidden="true" tabindex="-1"><div @class(['mx-cover', 'has-img' => $g->cover_path]) style="height:90px;border-radius:0;@if($g->cover_path)background-image:url('{{ $g->coverUrl() }}')@endif"></div></a>
          <div class="mx-card__pad mx-stack" style="gap:8px">
            <div><a href="{{ route('social.group', $g) }}" style="color:inherit"><b style="font-size:16px">{{ $g->name }}</b></a>
              @if ($g->isPrivate())<span class="mx-badge" title="{{ __('Groupe privé : adhésion sur approbation') }}"><x-mx.icon name="shield" :size="12"/> {{ __('Privé') }}</span>@endif
              @if ($g->is_demo)<span class="mx-demo-ribbon">{{ __('Démo') }}</span>@endif
              <div class="mx-xs mx-muted">Animé par {{ $g->mutual?->name }} · {{ $g->members_count }} membre(s)</div></div>
            @if ($g->description)<p class="mx-small">{{ \Illuminate\Support\Str::limit($g->description, 140) }}</p>@endif
            <div class="mx-row mx-between">
              <a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.group', $g) }}">{{ __('Voir') }}</a>
              @if ($st === 'active')<span class="mx-badge mx-badge--green">{{ __('Membre') }}</span>
              @elseif ($st === 'pending')<span class="mx-badge mx-badge--warn">{{ __('En attente') }}</span>
              @else
                @auth('member')<form method="POST" action="{{ route('social.group.join', $g) }}">@csrf<button class="mx-btn mx-btn--soft mx-btn--sm">{{ $g->isPrivate() ? __('Demander à rejoindre') : __('Rejoindre') }}</button></form>
                @else<a class="mx-btn mx-btn--soft mx-btn--sm" href="{{ route('social.login') }}">{{ __('Rejoindre') }}</a>@endauth
              @endif
            </div>
          </div>
        </article>
      @endforeach
    </div>
    {{ $groups->links('social.partials.pagination') }}
  @endif
</x-layouts.social>
