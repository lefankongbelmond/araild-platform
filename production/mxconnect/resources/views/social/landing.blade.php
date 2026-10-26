{{-- Accueil du réseau social (design Lot 2). Chiffres réels uniquement, états vides honnêtes. --}}
<x-layouts.social title="Le réseau des initiatives communautaires" active="communaute">
  <section class="mx-banner">
    <span class="mx-banner__pill">Bienvenue sur MX-CONNECT</span>
    <h1>Les initiatives qui changent la vie <em>méritent d’être visibles.</em></h1>
    <p style="margin-top:12px;max-width:560px">Rejoignez un réseau engagé pour la santé, la protection sociale et plus encore. Découvrez, partagez et soutenez les initiatives qui font avancer nos communautés.</p>
    <form class="mx-bigsearch" method="GET" action="{{ route('social.discover') }}" role="search">
      <label class="mx-sr" for="hm-q">Rechercher</label>
      <input id="hm-q" name="q" maxlength="100" placeholder="Rechercher une organisation, un projet, un service…">
      <button class="mx-btn mx-btn--primary" aria-label="Rechercher"><x-mx.icon name="search" :size="16"/></button>
    </form>
    <div class="mx-row mx-row--wrap" style="margin-top:16px;gap:10px">
      <a class="mx-btn mx-btn--primary" href="{{ route('social.discover') }}">Explorer les initiatives <x-mx.icon name="arrow-right" :size="16"/></a>
      <a class="mx-btn mx-btn--ghost" href="{{ route('social.register') }}">Rejoindre MX-CONNECT</a>
    </div>
    <div class="mx-bigstats" aria-label="Le réseau en chiffres">
      <div><i><x-mx.icon name="building" :size="18"/></i><span><b>{{ number_format($stats['organisations'], 0, ',', ' ') }}</b><span>Organisations</span></span></div>
      <div><i><x-mx.icon name="users" :size="18"/></i><span><b>{{ number_format($stats['membres'], 0, ',', ' ') }}</b><span>Membres</span></span></div>
      <div><i><x-mx.icon name="folder" :size="18"/></i><span><b>{{ number_format($stats['projets'], 0, ',', ' ') }}</b><span>Projets</span></span></div>
      <div><i><x-mx.icon name="calendar" :size="18"/></i><span><b>{{ number_format($stats['evenements'], 0, ',', ' ') }}</b><span>Événements à venir</span></span></div>
    </div>
  </section>

  <section class="mx-stack" style="gap:12px">
    <h2 style="font-size:17px">Explorer par domaine</h2>
    <div class="mx-domains">
      @foreach (\App\Support\SocialDomains::ALL as $code => $label)
        <a class="mx-card mx-domain" href="{{ route('social.discover', ['domaine' => $code]) }}"><i><x-mx.icon :name="'d-' . $code" :size="20"/></i>{{ $label }}<small>{{ $domains[$code] ?? 0 }} organisation(s)</small></a>
      @endforeach
    </div>
  </section>

  <section class="mx-stack" style="gap:12px">
    <div class="mx-row mx-between"><h2 style="font-size:17px"><x-mx.icon name="star" :size="18"/> À la une</h2><a class="mx-small" href="{{ route('social.projects') }}">Voir tout <x-mx.icon name="arrow-right" :size="14"/></a></div>
    @if ($projects->isEmpty())
      @include('social.partials.empty', ['icon' => 'folder', 'title' => 'Les premiers projets arrivent', 'text' => 'Les mutuelles et les organisations du réseau présenteront ici leurs projets et leurs résultats.'])
    @else
      <div class="mx-cards" style="grid-template-columns:repeat(auto-fill,minmax(230px,1fr))">@foreach ($projects as $p)@include('social.partials.project-tile', ['p' => $p])@endforeach</div>
    @endif
  </section>

  <section class="mx-stack" style="gap:12px">
    <div class="mx-row mx-between"><h2 style="font-size:17px"><x-mx.icon name="list" :size="18"/> Dernières publications</h2><a class="mx-small" href="{{ route('social.all') }}">Voir tout <x-mx.icon name="arrow-right" :size="14"/></a></div>
    <div class="mx-card">
      <div class="mx-mini">
        @forelse ($latest as $post)
          <a class="mx-mini__item" href="{{ route('social.post', $post) }}" style="color:inherit;align-items:flex-start">
            <x-mx.avatar :src="$post->authorAvatar()" :name="$post->authorName()" :org="(bool) $post->mutual_id" />
            <span class="mx-grow" style="min-width:0"><b>{{ $post->authorName() }}</b> <span class="mx-xs mx-muted">· {{ $post->published_at?->diffForHumans() }}</span>
              @if ($post->is_demo || $post->mutual?->profile?->is_demo)<span class="mx-demo-ribbon">Démo</span>@endif
              <span class="mx-small" style="display:block;overflow-wrap:anywhere">{{ \Illuminate\Support\Str::limit($post->body ?: $post->typeLabel(), 150) }}</span></span>
          </a>
        @empty
          <p class="mx-mini__item mx-small mx-muted">Les premières publications arrivent.</p>
        @endforelse
      </div>
    </div>
  </section>

  <div class="mx-grid-2">
    <section class="mx-card mx-card__pad mx-stack">
      <span class="mx-avatar" style="background:var(--mx-blue-soft);color:var(--mx-blue)"><x-mx.icon name="building" :size="20"/></span>
      <h2 style="font-size:18px">Votre organisation fait bouger les choses ?</h2>
      <p class="mx-small mx-muted">Créez sa page, publiez vos projets et vos offres, et recevez les engagements de bénévoles et de partenaires.</p>
      <div><a class="mx-btn mx-btn--primary" href="{{ route('social.pages.create') }}">Créer une organisation</a></div>
    </section>
    <section class="mx-card mx-card__pad mx-stack">
      <span class="mx-avatar" style="background:var(--mx-sky-soft);color:var(--mx-sky)"><x-mx.icon name="shield" :size="20"/></span>
      <h2 style="font-size:18px">Vous gérez une mutuelle de santé ?</h2>
      <p class="mx-small mx-muted">MX-CONNECT gère aussi vos adhérents, cotisations et prestations dans un espace sécurisé et totalement isolé.</p>
      <div class="mx-row mx-row--wrap" style="gap:8px"><a class="mx-btn mx-btn--ghost" href="{{ route('welcome') }}">Découvrir la solution</a><a class="mx-btn mx-btn--ghost" href="{{ route('comparator') }}">Comparer les offres</a></div>
    </section>
  </div>
</x-layouts.social>
