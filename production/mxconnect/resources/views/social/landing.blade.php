<x-layouts.social title="Le réseau social des mutuelles de santé" bare>
  <main id="contenu">
    <section class="mx-hero">
      <div class="mx-hero__in">
        <div>
          <div style="display:inline-block;background:#fff;border-radius:18px;padding:14px 18px;margin-bottom:22px;box-shadow:0 10px 30px rgba(0,0,0,.25)"><img src="/brand/mx-logo-full.webp" alt="MX-CONNECT" width="190" height="143" style="display:block"></div><br>
          <span class="mx-badge mx-badge--green" style="background:rgba(79,153,47,.22);color:#8ED46A">Réseau des mutuelles de santé</span>
          <h1 style="margin-top:16px">Ensemble pour une <em>meilleure santé</em>.</h1>
          <p class="lead">Suivez les mutuelles de votre région, découvrez leurs campagnes de prévention, leurs événements et leurs offres, et échangez avec la communauté.</p>
          <div class="mx-row mx-row--wrap" style="margin-top:28px">
            <a class="mx-btn mx-btn--primary mx-btn--lg" href="{{ route('social.register') }}">Créer un compte gratuit</a>
            <a class="mx-btn mx-btn--ghost mx-btn--lg" href="{{ route('social.home') }}">Voir les publications</a>
          </div>
          <p class="mx-small" style="margin-top:20px;color:#C9D6EE">
            @if ($stats['mutuals'] > 0)<b style="color:#fff">{{ $stats['mutuals'] }}</b> mutuelle(s) agréée(s) sur le réseau @endif
            @if ($stats['mutuals'] > 0 && $stats['posts'] > 0) · @endif
            @if ($stats['posts'] > 0)<b style="color:#fff">{{ $stats['posts'] }}</b> publication(s)@endif
          </p>
        </div>
        <div class="mx-hero-card mx-stack" aria-label="Dernières publications">
          @forelse ($latest as $post)
            <div class="mx-card mx-card__pad" style="color:var(--mx-text)">
              <div class="mx-row"><x-mx.avatar :src="$post->authorAvatar()" :name="$post->authorName()" :org="(bool) $post->mutual_id" size="sm" />
                <div class="mx-grow"><b class="mx-small">{{ $post->authorName() }}</b> @if($post->is_demo || $post->mutual?->profile?->is_demo)<span class="mx-demo-ribbon">Démo</span>@endif<div class="mx-xs mx-muted">{{ $post->typeLabel() }} · {{ $post->published_at?->diffForHumans() }}</div></div></div>
              <p class="mx-small" style="margin-top:8px">{{ \Illuminate\Support\Str::limit($post->body, 140) }}</p>
            </div>
          @empty
            <div class="mx-card mx-card__pad" style="color:var(--mx-text)"><b>Les premières publications arrivent.</b><p class="mx-small mx-muted">Les mutuelles du réseau publient ici leurs annonces et campagnes.</p></div>
          @endforelse
        </div>
      </div>
    </section>

    <section class="mx-section">
      <h2>Ce que vous pouvez faire</h2>
      <div class="mx-grid-3" style="margin-top:24px">
        <div class="mx-card mx-feature"><div class="mx-ico"><x-mx.icon name="building"/></div><h3>Découvrir les mutuelles</h3><p class="mx-small mx-muted">Annuaire, pages officielles, zones couvertes et coordonnées.</p></div>
        <div class="mx-card mx-feature"><div class="mx-ico mx-ico--green"><x-mx.icon name="megaphone"/></div><h3>Suivre leurs actions</h3><p class="mx-small mx-muted">Campagnes de sensibilisation, dépistages, journées communautaires.</p></div>
        <div class="mx-card mx-feature"><div class="mx-ico"><x-mx.icon name="users"/></div><h3>Échanger</h3><p class="mx-small mx-muted">Réagissez, commentez, partagez et enregistrez les informations utiles.</p></div>
      </div>
    </section>

    @if ($mutuals->isNotEmpty())
    <section class="mx-section" style="padding-top:0">
      <div class="mx-row mx-between"><h2>Mutuelles du réseau</h2><a href="{{ route('social.mutuals') }}">Voir l'annuaire</a></div>
      <div class="mx-card" style="margin-top:18px"><div class="mx-mini">@foreach ($mutuals as $m) @include('social.partials.mutual-mini', ['m' => $m]) @endforeach</div></div>
    </section>
    @endif
  </main>
  <footer class="mx-footer">
    <div class="mx-footer__in">
      <span>© {{ date('Y') }} MX-CONNECT — Ensemble pour une meilleure santé.</span>
      <span class="mx-row mx-row--wrap">
        <a href="{{ route('social.about') }}">À propos</a>
        <a href="{{ route('social.privacy') }}">Confidentialité</a>
        <a href="{{ route('comparator') }}">Comparateur d'offres</a>
        <a href="{{ route('welcome') }}">Solution de gestion</a>
        <a href="{{ route('network.login') }}">Administration du réseau</a>
      </span>
    </div>
  </footer>
</x-layouts.social>
