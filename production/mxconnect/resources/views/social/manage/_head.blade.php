{{-- En-tête de l'espace de gestion d'une page : $page, $tab, $canInbox, $openRequests --}}
<section class="mx-card mx-card__pad mx-stack" style="gap:10px">
  <div class="mx-row" style="gap:12px;min-width:0">
    <x-mx.avatar :src="$page->profile?->logoUrl()" :name="$page->name" org />
    <div style="min-width:0"><p class="mx-xs mx-muted">Gérer la page</p>
      <a href="{{ route('social.mutual', $page->slug) }}" style="color:inherit"><b class="mx-truncate" style="display:block;font-size:17px">{{ $page->name }}</b></a></div>
  </div>
  <nav class="mx-segment" aria-label="Gestion de la page">
    <a href="{{ route('social.manage.projects', $page->slug) }}" @class(['is-active' => $tab === 'projets'])>Projets</a>
    <a href="{{ route('social.manage.opportunities', $page->slug) }}" @class(['is-active' => $tab === 'opportunites'])>Opportunités</a>
    @if ($canInbox)
      <a href="{{ route('social.manage.requests', $page->slug) }}" @class(['is-active' => $tab === 'demandes'])>Demandes reçues @if($openRequests)({{ $openRequests }})@endif</a>
    @endif
  </nav>
</section>
