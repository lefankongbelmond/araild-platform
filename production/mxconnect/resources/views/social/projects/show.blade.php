@php
  $viewer = auth('member')->user();
  $I = \App\Models\Social\ProjectIndicator::class;
  $place = $project->zone ?: collect([$project->city, $project->region])->filter()->implode(', ');
  $actionUrl = fn ($type) => $viewer ? route('social.request.create', ['type' => $type, 'projet' => $project->slug]) : route('social.login');
@endphp
<x-layouts.social :title="$project->title" active="projets">
  <x-slot:head>
    <meta property="og:title" content="{{ $project->title }} · MX-CONNECT">
    <meta property="og:description" content="{{ $project->summary }}">
  </x-slot:head>

  <a class="mx-small" href="{{ route('social.projects') }}"><x-mx.icon name="arrow-left" :size="14"/> Tous les projets</a>

  <section class="mx-card" style="overflow:hidden">
    <div class="mx-pagecover" @if($project->cover_path) style="background-image:url('{{ $project->coverUrl() }}')" @endif></div>
    <div class="mx-card__pad mx-stack">
      @if ($project->status !== 'published')
        <div class="mx-alert mx-alert--warn">{{ $project->status === 'draft' ? 'Brouillon : ce projet n’est visible que par les gestionnaires de la page.' : 'Ce projet a été masqué par la modération du réseau.' }}</div>
      @endif
      <div class="mx-tags">
        <span class="mx-badge mx-badge--blue">{{ $project->phaseLabel() }}</span>
        @if ($isDemo)<span class="mx-demo-ribbon" title="Contenu fictif de démonstration">Démo</span>@endif
      </div>
      <h1 style="font-size:24px;overflow-wrap:anywhere">{{ $project->title }}</h1>
      <a class="mx-row" href="{{ route('social.mutual', $page->slug) }}" style="color:inherit;gap:10px">
        <x-mx.avatar :src="$page->profile?->logoUrl()" :name="$page->name" org size="sm" />
        <span class="mx-small"><b>{{ $page->name }}</b>@if($page->isVerified()) <span class="mx-verified" title="Organisation vérifiée par l’administration du réseau"><x-mx.icon name="verified" :size="14"/></span>@endif
          <span class="mx-muted">· {{ $page->isMemberPage() ? 'Page créée par un membre' : (\App\Models\Social\MutualProfile::CATEGORIES[$page->profile?->category ?? 'mutuelle'] ?? 'Mutuelle de santé') }}</span></span>
      </a>
      <p>{{ $project->summary }}</p>
      <div class="mx-meta">
        @if ($place)<span><x-mx.icon name="pin" :size="14"/> {{ $place }}</span>@endif
        @if ($project->starts_on)<span><x-mx.icon name="calendar" :size="14"/> {{ $project->ends_on ? 'Du ' . $project->starts_on->format('d/m/Y') . ' au ' . $project->ends_on->format('d/m/Y') : 'Depuis le ' . $project->starts_on->format('d/m/Y') }}</span>@endif
      </div>

      {{-- Les 3 actions : chacune n'apparaît que si elle a un sens pour ce projet. --}}
      <div class="mx-actionbar">
        <a class="mx-btn mx-btn--soft" href="#resultats"><x-mx.icon name="chart" :size="16"/> Voir le projet et les résultats</a>
        @if ($canManage)
          <a class="mx-btn mx-btn--primary" href="{{ route('social.manage.project.edit', [$page->slug, $project->id]) }}"><x-mx.icon name="edit" :size="16"/> Gérer ce projet</a>
        @elseif (! $isDemo && $project->isPublic())
          @if ($project->seeks_volunteers)<a class="mx-btn mx-btn--primary" href="{{ $actionUrl('engagement') }}"><x-mx.icon name="hand" :size="16"/> Je m’engage</a>@endif
          @if ($project->seeks_partners)<a class="mx-btn mx-btn--green" href="{{ $actionUrl('support') }}"><x-mx.icon name="handshake" :size="16"/> Soutenir ou devenir partenaire</a>@endif
        @endif
      </div>
      @if ($isDemo)<p class="mx-xs mx-muted">Projet fictif de démonstration : aucune demande ne peut être envoyée.</p>@endif
    </div>
  </section>

  @if ($project->description || $project->objectives)
    <section class="mx-card mx-card__pad mx-stack">
      <h2 class="mx-card__title">Le projet</h2>
      @if ($project->description)<p style="white-space:pre-line">{{ $project->description }}</p>@endif
      @if ($project->objectives)<h3 style="font-size:15px">Objectifs</h3><p style="white-space:pre-line">{{ $project->objectives }}</p>@endif
    </section>
  @endif

  <section class="mx-card mx-card__pad mx-stack" id="resultats">
    <h2 class="mx-card__title">Résultats</h2>
    @if ($project->indicators->isEmpty())
      <p class="mx-small mx-muted">L’organisation n’a pas encore publié de résultats chiffrés pour ce projet.</p>
    @else
      <div class="mx-results">
        @foreach ($project->indicators as $i)
          <div class="mx-result">
            <span class="mx-result__value">{{ $I::fmt($i->value) }}@if($i->unit) <span style="font-size:15px;font-weight:600">{{ $i->unit }}</span>@endif</span>
            <span class="mx-result__label">{{ $i->label }}</span>
            @if ($i->target)
              <span class="mx-bar" role="img" aria-label="{{ $i->percent() }} % de l’objectif"><i style="width:{{ $i->percent() }}%"></i></span>
              <span class="mx-xs mx-muted">Objectif : {{ $I::fmt($i->target) }}{{ $i->unit ? ' ' . $i->unit : '' }} ({{ $i->percent() }} %)</span>
            @endif
            <span class="mx-xs mx-muted">@if($i->period){{ $i->period }} · @endif @if($i->source_note)Source : {{ $i->source_note }}@endif</span>
            @if ($i->isVerified())
              <span><span class="mx-badge mx-badge--verified" title="Contrôlé par l’administration du réseau le {{ $i->verified_at->format('d/m/Y') }}"><x-mx.icon name="check" :size="12"/> Vérifié</span></span>
            @else
              <span><span class="mx-badge mx-badge--declared" title="Chiffre publié par l’organisation, non contrôlé par le réseau">Déclaré</span></span>
            @endif
          </div>
        @endforeach
      </div>
      <p class="mx-xs mx-muted">« Déclaré » : chiffre publié sous la responsabilité de {{ $page->name }}. « Vérifié » : chiffre contrôlé par l’administration du réseau MX-CONNECT sur pièces.</p>
    @endif
  </section>

  @if ($project->seeks_volunteers || $project->seeks_partners || $project->partners)
    <section class="mx-card mx-card__pad mx-stack">
      <h2 class="mx-card__title">Besoins et partenaires</h2>
      @if ($project->seeks_volunteers)<div><h3 style="font-size:15px">Bénévoles recherchés</h3><p class="mx-small" style="white-space:pre-line">{{ $project->volunteer_needs }}</p></div>@endif
      @if ($project->seeks_partners)<div><h3 style="font-size:15px">Soutien recherché</h3><p class="mx-small" style="white-space:pre-line">{{ $project->partner_needs }}</p>
        <p class="mx-xs mx-muted">MX-CONNECT ne collecte aucun fonds : un soutien financier se convient directement avec l’organisation.</p></div>@endif
      @if ($project->partners)<div><h3 style="font-size:15px">Partenaires (déclarés par l’organisation)</h3><p class="mx-small" style="white-space:pre-line">{{ $project->partners }}</p></div>@endif
    </section>
  @endif

  @if ($opportunities->isNotEmpty())
    <section class="mx-stack">
      <h2 class="mx-card__title" style="padding:0 6px">Offres liées à ce projet</h2>
      @foreach ($opportunities as $o)@include('social.opportunities._card', ['o' => $o->setRelation('mutual', $page)])@endforeach
    </section>
  @endif

  <section class="mx-card mx-card__pad mx-stack" id="actualites">
    <h2 class="mx-card__title">Avancement</h2>
    @forelse ($project->updates as $u)
      <article style="border-left:3px solid var(--mx-blue-soft);padding-left:12px">
        <b>{{ $u->title }}</b> <span class="mx-xs mx-muted">· {{ $u->created_at->format('d/m/Y') }}</span>
        <p class="mx-small" style="white-space:pre-line;margin-top:4px">{{ $u->body }}</p>
      </article>
    @empty
      <p class="mx-small mx-muted">Aucune actualité d’avancement pour le moment.</p>
    @endforelse
  </section>

  <x-slot:right>
    <section class="mx-card mx-card__pad mx-stack" style="gap:8px">
      <h2 class="mx-card__title">Porté par</h2>
      <a class="mx-row" href="{{ route('social.mutual', $page->slug) }}" style="color:inherit;gap:10px"><x-mx.avatar :src="$page->profile?->logoUrl()" :name="$page->name" org /><b class="mx-truncate">{{ $page->name }}</b></a>
      @if ($page->profile?->tagline)<p class="mx-small">{{ $page->profile->tagline }}</p>@endif
      <a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.mutual', [$page->slug, 'onglet' => 'projets']) }}">Tous ses projets</a>
    </section>
    @include('social.partials.sidebar')
  </x-slot:right>
</x-layouts.social>
