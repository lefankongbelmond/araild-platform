@php
  $feed = app(\App\Services\Social\FeedService::class);
  $suggest = $feed->suggestions(auth('member')->user(), 4);
  $events = $feed->upcomingEvents(3);
  $campaigns = $feed->campaigns(2);
  $trends = $feed->trends(5);
  // Offres ouvertes les plus récentes (mises en cache 5 min ; vide tant que la migration n'est pas passée).
  $latestOpps = \Illuminate\Support\Facades\Cache::remember('social:sb-opps', 300, fn () => \Illuminate\Support\Facades\Schema::connection('central')->hasTable('social_opportunities')
      ? \App\Models\Social\Opportunity::public()->open()->with('mutual:id,name')->latest('published_at')->limit(3)->get() : collect());
@endphp
@if (! empty($trends))
<section class="mx-card" aria-labelledby="sb-trends">
  <div class="mx-card__head"><h2 class="mx-card__title" id="sb-trends"><x-mx.icon name="hash" :size="18"/> Tendances de la semaine</h2></div>
  <div class="mx-card__pad mx-trends" style="padding-top:4px">
    @foreach ($trends as $tag => $n)<a href="{{ route('social.search', ['q' => '#' . $tag]) }}">#{{ $tag }} <span>{{ $n }} publication{{ $n > 1 ? 's' : '' }}</span></a>@endforeach
  </div>
</section>
@endif

<section class="mx-card" aria-labelledby="sb-discover">
  <div class="mx-card__head"><h2 class="mx-card__title" id="sb-discover">Organisations à découvrir</h2><a class="mx-small" href="{{ route('social.mutuals') }}">Tout voir</a></div>
  <div class="mx-mini">
    @forelse ($suggest as $m)
      @include('social.partials.mutual-mini', ['m' => $m])
    @empty
      <p class="mx-mini__item mx-small mx-muted">Vous suivez déjà toutes les mutuelles.</p>
    @endforelse
  </div>
</section>

<section class="mx-card" aria-labelledby="sb-events">
  <div class="mx-card__head"><h2 class="mx-card__title" id="sb-events"><x-mx.icon name="calendar" :size="18"/> Événements à venir</h2><a class="mx-small" href="{{ route('social.events') }}">Agenda</a></div>
  <div class="mx-mini">
    @forelse ($events as $e)
      <a class="mx-mini__item" href="{{ route('social.post', $e) }}" style="color:inherit">
        <span class="mx-cal" style="background:var(--mx-blue-soft);border-radius:10px;padding:4px 8px"><b>{{ $e->event_starts_at->format('d') }}</b><span>{{ $e->event_starts_at->translatedFormat('M') }}</span></span>
        <span class="mx-grow"><b class="mx-truncate">{{ \Illuminate\Support\Str::limit($e->body, 60) }}</b><span class="mx-xs mx-muted">{{ $e->mutual?->name }}@if($e->event_location) · {{ $e->event_location }}@endif</span></span>
      </a>
    @empty
      <p class="mx-mini__item mx-small mx-muted">Aucun événement annoncé pour le moment.</p>
    @endforelse
  </div>
</section>

@if ($latestOpps->isNotEmpty() && ! request()->routeIs('social.opportunities', 'social.opportunity'))
<section class="mx-card" aria-labelledby="sb-opps">
  <div class="mx-card__head"><h2 class="mx-card__title" id="sb-opps"><x-mx.icon name="rocket" :size="18"/> Opportunités récentes</h2><a class="mx-small" href="{{ route('social.opportunities') }}">Tout voir</a></div>
  <div class="mx-mini">
    @foreach ($latestOpps as $o)
      <a class="mx-mini__item" href="{{ route('social.opportunity', $o) }}" style="color:inherit">
        <span class="mx-avatar mx-avatar--sm" style="background:var(--mx-blue-soft);color:var(--mx-blue)"><x-mx.icon name="briefcase" :size="16"/></span>
        <span class="mx-grow"><b class="mx-truncate">{{ $o->title }}</b><span class="mx-xs mx-muted">{{ $o->categoryLabel() }} · {{ $o->mutual?->name }}</span></span>
      </a>
    @endforeach
  </div>
</section>
@endif

@if ($campaigns->isNotEmpty())
<section class="mx-card" aria-labelledby="sb-camp">
  <div class="mx-card__head"><h2 class="mx-card__title" id="sb-camp">Campagnes de santé</h2></div>
  <div class="mx-mini">
    @foreach ($campaigns as $c)
      <a class="mx-mini__item" href="{{ route('social.post', $c) }}" style="color:inherit">
        <span class="mx-avatar mx-avatar--sm mx-avatar--org" style="background:var(--mx-green-soft);color:var(--mx-green)"><x-mx.icon name="megaphone" :size="16"/></span>
        <span class="mx-grow"><b class="mx-truncate">{{ \Illuminate\Support\Str::limit($c->body, 60) }}</b><span class="mx-xs mx-muted">{{ $c->mutual?->name }}</span></span>
      </a>
    @endforeach
  </div>
</section>
@endif
<p class="mx-xs mx-muted" style="padding:0 6px">MX-CONNECT — Ensemble pour une meilleure santé.<br>© {{ date('Y') }} · <a href="{{ route('social.about') }}">À propos</a> · <a href="{{ route('social.privacy') }}">Confidentialité</a></p>
