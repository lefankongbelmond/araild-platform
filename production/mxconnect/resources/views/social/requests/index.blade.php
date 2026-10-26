<x-layouts.social title="Mes candidatures et engagements" active="demandes">
  <h1 style="font-size:22px">Mes candidatures et engagements</h1>
  <p class="mx-small mx-muted">Vos candidatures, vos propositions d’engagement et de soutien, et leurs réponses. <a href="{{ route('social.opportunities') }}">Chercher une opportunité</a> · <a href="{{ route('social.projects') }}">Voir les projets</a></p>
  @forelse ($items as $r)
    <a class="mx-card mx-card__pad mx-row" href="{{ route('social.request', $r->reference) }}" style="color:inherit;gap:12px">
      <x-mx.avatar :src="$r->mutual?->profile?->logoUrl()" :name="$r->mutual?->name ?? '?'" org />
      <span class="mx-grow" style="min-width:0"><b class="mx-truncate" style="display:block">{{ $r->kindLabel() }} · {{ $r->subject() }}</b>
        <span class="mx-xs mx-muted" style="display:block">{{ $r->mutual?->name }} · {{ $r->reference }} · {{ $r->created_at->format('d/m/Y') }}</span></span>
      <span class="mx-status mx-status--{{ $r->statusClass() }}">{{ $r->statusLabel() }}</span>
    </a>
  @empty
    @include('social.partials.empty', ['icon' => 'briefcase', 'title' => 'Aucune demande', 'text' => 'Postulez à une offre, engagez-vous sur un projet ou proposez votre soutien à une organisation.', 'action' => ['Voir les opportunités', route('social.opportunities')]])
  @endforelse
  {{ $items->links('social.partials.pagination') }}
</x-layouts.social>
