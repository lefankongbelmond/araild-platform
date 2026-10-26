@php
  $viewer = auth('member')->user();
  $O = \App\Models\Social\Opportunity::class;
  $open = $o->isOpen();
  $applyLabel = $o->isJob() ? 'Postuler' : 'Manifester mon intérêt';
@endphp
<x-layouts.social :title="$o->title" active="opportunites">
  <x-slot:head>
    <meta property="og:title" content="{{ $o->title }} · {{ $page->name }} · MX-CONNECT">
    <meta property="og:description" content="{{ $o->categoryLabel() }}{{ $o->place() ? ' — ' . $o->place() : '' }}">
  </x-slot:head>

  <a class="mx-small" href="{{ route('social.opportunities') }}"><x-mx.icon name="arrow-left" :size="14"/> Emplois et opportunités</a>

  <section class="mx-card mx-card__pad mx-stack">
    @if ($o->status !== 'published')
      <div class="mx-alert mx-alert--warn">{{ $o->status === 'draft' ? 'Brouillon : visible seulement par les gestionnaires de la page.' : 'Cette offre a été masquée par la modération du réseau.' }}</div>
    @endif
    <div class="mx-tags">
      <span class="mx-badge mx-badge--blue">{{ $o->categoryLabel() }}</span>
      <span @class(['mx-status', 'mx-status--open' => $open, 'mx-status--closed' => ! $open])>{{ $o->availabilityLabel() }}</span>
      @if ($isDemo)<span class="mx-demo-ribbon" title="Contenu fictif de démonstration">Démo</span>@endif
    </div>
    <h1 style="font-size:24px;overflow-wrap:anywhere">{{ $o->title }}</h1>
    <a class="mx-row" href="{{ route('social.mutual', $page->slug) }}" style="color:inherit;gap:10px">
      <x-mx.avatar :src="$page->profile?->logoUrl()" :name="$page->name" org size="sm" />
      <span class="mx-small"><b>{{ $page->name }}</b>@if($page->isVerified()) <span class="mx-verified" title="Organisation vérifiée par l’administration du réseau"><x-mx.icon name="verified" :size="14"/></span>@endif
        @if ($page->isMemberPage())<span class="mx-muted">· Page créée par un membre (non vérifiée)</span>@endif</span>
    </a>
    <dl class="mx-kv">
      @if ($o->contract_type)<dt>Contrat</dt><dd>{{ $O::CONTRACTS[$o->contract_type] ?? $o->contract_type }}</dd>@endif
      @if ($o->place())<dt>Lieu</dt><dd>{{ $o->place() }}@if($o->workplace) · {{ $O::WORKPLACES[$o->workplace] }}@endif</dd>
      @elseif ($o->workplace)<dt>Lieu</dt><dd>{{ $O::WORKPLACES[$o->workplace] }}</dd>@endif
      @if ($o->remuneration)<dt>Rémunération</dt><dd>{{ $o->remuneration }}</dd>@endif
      @if ($o->positions)<dt>Postes / places</dt><dd>{{ $o->positions }}</dd>@endif
      @if ($o->deadline_on)<dt>Date limite</dt><dd>{{ $o->deadline_on->translatedFormat('l j F Y') }}</dd>@endif
      @if ($o->project)<dt>Projet</dt><dd><a href="{{ route('social.project', $o->project) }}">{{ $o->project->title }}</a></dd>@endif
      <dt>Publiée le</dt><dd>{{ ($o->published_at ?? $o->created_at)->format('d/m/Y') }}</dd>
    </dl>

    <div class="mx-actionbar">
      @if ($canManage)
        <a class="mx-btn mx-btn--primary" href="{{ route('social.manage.opportunity.edit', [$page->slug, $o->id]) }}"><x-mx.icon name="edit" :size="16"/> Gérer cette offre</a>
      @elseif ($mine && $mine->isOpen())
        <a class="mx-btn mx-btn--soft" href="{{ route('social.request', $mine->reference) }}"><x-mx.icon name="file" :size="16"/> Ma candidature : {{ mb_strtolower($mine->statusLabel()) }}</a>
      @elseif ($isDemo)
        <span class="mx-small mx-muted">Offre fictive de démonstration : aucune candidature possible.</span>
      @elseif ($open && $o->isPublic())
        @if ($o->acceptsPlatform())
          <a class="mx-btn mx-btn--primary" href="{{ $viewer ? route('social.request.create', ['type' => 'application', 'offre' => $o->slug]) : route('social.login') }}"><x-mx.icon name="send" :size="16"/> {{ $applyLabel }}</a>
        @else
          <a class="mx-btn mx-btn--primary" href="{{ $o->apply_url }}" target="_blank" rel="noopener nofollow"><x-mx.icon name="link" :size="16"/> {{ $applyLabel }} sur le site de l’organisation</a>
        @endif
      @else
        <span class="mx-small mx-muted">Cette offre n’accepte plus de candidatures.</span>
      @endif
      @if ($o->project && ! $canManage)
        <a class="mx-btn mx-btn--ghost" href="{{ route('social.project', $o->project) }}"><x-mx.icon name="chart" :size="16"/> Voir le projet et les résultats</a>
      @endif
    </div>
    @if ($mine && ! $mine->isOpen())<p class="mx-xs mx-muted">Votre précédente demande ({{ $mine->reference }}) : {{ mb_strtolower($mine->statusLabel()) }}.</p>@endif
  </section>

  <section class="mx-card mx-card__pad mx-stack">
    <h2 class="mx-card__title">Description</h2>
    <p style="white-space:pre-line;overflow-wrap:anywhere">{{ $o->description }}</p>
    @if ($o->requirements)<h3 style="font-size:15px">Profil recherché</h3><p style="white-space:pre-line;overflow-wrap:anywhere">{{ $o->requirements }}</p>@endif
    @if ($o->apply_instructions)<h3 style="font-size:15px">Comment postuler</h3><p class="mx-small" style="white-space:pre-line">{{ $o->apply_instructions }}</p>@endif
  </section>

  <p class="mx-xs mx-muted" style="padding:0 6px">Offre publiée sous la responsabilité de {{ $page->name }}. MX-CONNECT ne demande jamais de paiement pour une candidature.
    @if ($viewer && ! $canManage)<button type="button" class="mx-btn mx-btn--ghost mx-btn--sm" data-report="{{ route('social.mutual.report', $page->slug) }}"><x-mx.icon name="flag" :size="14"/> Signaler</button>@endif</p>

  <x-slot:right>
    @if ($similar->isNotEmpty())
      <section class="mx-card">
        <div class="mx-card__head"><h2 class="mx-card__title">Offres similaires</h2></div>
        <div class="mx-mini">@foreach ($similar as $s)<a class="mx-mini__item" href="{{ route('social.opportunity', $s) }}" style="color:inherit"><span class="mx-avatar mx-avatar--sm" style="background:var(--mx-blue-soft);color:var(--mx-blue)"><x-mx.icon name="briefcase" :size="16"/></span><span class="mx-grow"><b class="mx-truncate">{{ $s->title }}</b><span class="mx-xs mx-muted">{{ $s->mutual?->name }}</span></span></a>@endforeach</div>
      </section>
    @endif
    @include('social.partials.sidebar')
  </x-slot:right>
</x-layouts.social>
