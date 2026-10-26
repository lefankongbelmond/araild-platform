@php
  $viewer = auth('member')->user();
  $I = \App\Models\Social\ProjectIndicator::class;
  $place = $project->zone ?: collect([$project->city, $project->region])->filter()->implode(', ');
  $actionUrl = fn ($type) => $viewer ? route('social.request.create', ['type' => $type, 'projet' => $project->slug]) : route('social.login');
  $canAct = ! $canManage && ! $isDemo && $project->isPublic();
  $partners = collect(preg_split('/\R+/', (string) $project->partners))->map(fn ($l) => trim($l, " \t-•"))->filter();
@endphp
<x-layouts.social :title="$project->title" active="projets" wide>
  <x-slot:head>
    <meta property="og:title" content="{{ $project->title }} · MX-CONNECT">
    <meta property="og:description" content="{{ $project->summary }}">
  </x-slot:head>

  <a class="mx-small" href="{{ route('social.mutual', [$page->slug, 'onglet' => 'projets']) }}"><x-mx.icon name="arrow-left" :size="14"/> {{ $page->name }}</a>

  @if ($project->status !== 'published')
    <div class="mx-alert mx-alert--warn">{{ $project->status === 'draft' ? 'Brouillon : ce projet n’est visible que par les gestionnaires de la page.' : 'Ce projet a été masqué par la modération du réseau.' }}</div>
  @endif

  <section class="mx-card" style="overflow:hidden">
    <div @class(['mx-phero', 'has-img' => $project->cover_path]) @if($project->cover_path) style="background-image:url('{{ $project->coverUrl() }}')" @endif>
      <span class="mx-badge">{{ $project->phaseLabel() }}</span>
      <h1>{{ $project->title }}@if($isDemo) <span class="mx-demo-ribbon" title="Contenu fictif de démonstration">Démo</span>@endif</h1>
      <p>{{ $project->summary }}</p>
    </div>
    <div class="mx-pbar">
      <a class="mx-row" href="{{ route('social.mutual', $page->slug) }}" style="color:inherit;gap:10px;min-width:0">
        <x-mx.avatar :src="$page->profile?->logoUrl()" :name="$page->name" org />
        <span style="min-width:0"><span class="mx-xs mx-muted" style="display:block">Porté par</span><b class="mx-truncate" style="display:block">{{ $page->name }}@if($page->isVerified()) <span class="mx-verified" title="Organisation vérifiée par l’administration du réseau"><x-mx.icon name="verified" :size="14"/></span>@endif</b></span>
      </a>
      <div class="mx-row mx-row--wrap" style="gap:8px">
        @if ($canManage)
          <a class="mx-btn mx-btn--primary" href="{{ route('social.manage.project.edit', [$page->slug, $project->id]) }}"><x-mx.icon name="edit" :size="16"/> Gérer ce projet</a>
        @elseif ($canAct)
          @if ($project->seeks_volunteers)<a class="mx-btn mx-btn--primary" href="{{ $actionUrl('engagement') }}"><x-mx.icon name="hand" :size="16"/> Je m’engage</a>@endif
          @if ($project->seeks_partners)<a class="mx-btn mx-btn--ghost mx-btn--support" href="{{ $actionUrl('support') }}" style="color:var(--mx-sun)"><x-mx.icon name="gift" :size="16"/> Soutenir</a>@endif
        @endif
        <button type="button" class="mx-btn mx-btn--ghost" data-copy="{{ route('social.project', $project) }}"><x-mx.icon name="share-out" :size="16"/> Partager</button>
      </div>
    </div>
    <div class="mx-kpis">
      <div><small>Avancement</small>@if ($project->progress !== null)<b style="font-size:20px">{{ $project->progress }} %</b><span class="mx-bar" role="img" aria-label="{{ $project->progress }} % (déclaré)"><i style="width:{{ $project->progress }}%"></i></span>@else<b>{{ $project->phaseLabel() }}</b>@endif</div>
      <div><small>Zone d’intervention</small><b>{{ $place ?: 'Non précisée' }}</b></div>
      <div><small>Calendrier</small><b>@if($project->starts_on){{ $project->starts_on->translatedFormat('M Y') }} → {{ $project->ends_on?->translatedFormat('M Y') ?? '…' }}@else Non précisé @endif</b></div>
      <div><small>Bénéficiaires</small><b>{{ $project->beneficiaries ?: 'Non précisé' }}</b></div>
    </div>
  </section>
  @if ($isDemo)<p class="mx-xs mx-muted">Projet fictif de démonstration : aucune demande ne peut être envoyée.</p>@endif

  <div class="mx-2col">
    <div class="mx-stack">
      @if ($project->objectives || $project->description)
        <section class="mx-card mx-card__pad mx-stack">
          @if ($project->objectives)<h2 class="mx-card__title"><x-mx.icon name="target" :size="18"/> Objectifs</h2><p style="white-space:pre-line">{{ $project->objectives }}</p>@endif
          @if ($project->description)<h2 class="mx-card__title" @if($project->objectives) style="margin-top:6px" @endif>Le projet</h2><p style="white-space:pre-line;overflow-wrap:anywhere">{{ $project->description }}</p>@endif
          @if (! empty($project->domains))<div class="mx-dtags">@foreach ($project->domains as $d)@if(\App\Support\SocialDomains::label($d))<a class="mx-dtag" href="{{ route('social.discover', ['onglet' => 'projets', 'domaine' => $d]) }}">{{ \App\Support\SocialDomains::label($d) }}</a>@endif @endforeach</div>@endif
        </section>
      @endif

      <section class="mx-card mx-card__pad mx-stack" id="resultats">
        <h2 class="mx-card__title"><x-mx.icon name="chart" :size="18"/> Indicateurs et résultats</h2>
        <p class="mx-xs mx-muted">Les résultats « déclarés » sont communiqués par l’organisation. Seuls ceux marqués « vérifiés » ont été contrôlés par le réseau.</p>
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
                @if ($i->isVerified())
                  <span><span class="mx-badge mx-badge--verified" title="Contrôlé par l’administration du réseau le {{ $i->verified_at->format('d/m/Y') }}"><x-mx.icon name="check" :size="12"/> Vérifié par le réseau</span></span>
                @else
                  <span><span class="mx-badge mx-badge--declared" title="Chiffre publié par l’organisation, non contrôlé par le réseau">Déclaré par l’organisation</span></span>
                @endif
                @if ($i->period || $i->source_note)<span class="mx-xs mx-muted">@if($i->period){{ $i->period }}@endif @if($i->period && $i->source_note) · @endif @if($i->source_note)Source : {{ $i->source_note }}@endif</span>@endif
              </div>
            @endforeach
          </div>
        @endif
      </section>

      <section class="mx-card mx-card__pad mx-stack" id="actualites">
        <h2 class="mx-card__title"><x-mx.icon name="clock" :size="18"/> Mises à jour et prochaines étapes</h2>
        @if ($project->updates->isEmpty())
          <p class="mx-small mx-muted">Aucune actualité d’avancement pour le moment.</p>
        @else
          <ol class="mx-timeline">
            @foreach ($project->updates as $u)
              <li><span class="mx-xs mx-muted">{{ $u->created_at->translatedFormat('j F Y') }}</span><b style="display:block;color:var(--mx-heading)">{{ $u->title }}</b><p class="mx-small" style="white-space:pre-line">{{ $u->body }}</p></li>
            @endforeach
          </ol>
        @endif
      </section>

      @if ($opportunities->isNotEmpty())
        <section class="mx-stack">
          <h2 class="mx-card__title" style="padding:0 6px">Offres liées à ce projet</h2>
          @foreach ($opportunities as $o)@include('social.opportunities._card', ['o' => $o->setRelation('mutual', $page)])@endforeach
        </section>
      @endif
    </div>

    <aside class="mx-stack">
      @if ($partners->isNotEmpty())
        <section class="mx-card mx-card__pad mx-stack" style="gap:10px">
          <h2 class="mx-card__title">Partenaires</h2>
          <ul class="mx-checklist">@foreach ($partners as $pt)<li><x-mx.icon name="check" :size="16"/><span>{{ $pt }}</span></li>@endforeach</ul>
          <p class="mx-xs mx-muted">Déclarés par l’organisation.</p>
        </section>
      @endif
      @if ($project->seeks_volunteers || $project->seeks_partners)
        <section class="mx-card mx-card__pad mx-stack" style="gap:10px">
          <h2 class="mx-card__title">Besoins</h2>
          @if ($project->seeks_volunteers)<div><b class="mx-small">Bénévoles recherchés</b><p class="mx-small" style="white-space:pre-line">{{ $project->volunteer_needs }}</p>
            @if ($canAct)<a class="mx-btn mx-btn--soft mx-btn--sm" style="margin-top:6px" href="{{ $actionUrl('engagement') }}"><x-mx.icon name="hand" :size="14"/> Je m’engage</a>@endif</div>@endif
          @if ($project->seeks_partners)<div><b class="mx-small">Soutien recherché</b><p class="mx-small" style="white-space:pre-line">{{ $project->partner_needs }}</p>
            @if ($canAct)<a class="mx-btn mx-btn--ghost mx-btn--sm" style="margin-top:6px;color:var(--mx-sun)" href="{{ $actionUrl('support') }}"><x-mx.icon name="gift" :size="14"/> Soutenir ou devenir partenaire</a>@endif</div>
            <p class="mx-xs mx-muted">MX-CONNECT ne collecte aucun fonds : un soutien financier se convient directement avec l’organisation.</p>@endif
        </section>
      @endif
      <section class="mx-card mx-card__pad mx-stack" style="gap:10px">
        <h2 class="mx-card__title">Publications liées</h2>
        @forelse ($related as $rp)
          <a href="{{ route('social.post', $rp) }}" style="color:inherit;display:block"><span class="mx-small" style="display:block;overflow-wrap:anywhere">{{ \Illuminate\Support\Str::limit($rp->body, 110) }}</span><span class="mx-xs mx-muted">{{ $rp->published_at?->diffForHumans() }}</span></a>
        @empty
          <p class="mx-small mx-muted">Aucune publication liée pour le moment.</p>
        @endforelse
      </section>
    </aside>
  </div>
</x-layouts.social>
