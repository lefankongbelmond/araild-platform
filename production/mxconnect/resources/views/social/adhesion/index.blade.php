<x-layouts.social title="Mes demandes d’adhésion" active="adhesions">
  <h1 style="font-size:22px">Mes demandes d’adhésion</h1>
  <p class="mx-small mx-muted">Suivre une mutuelle n’est pas adhérer : l’adhésion se demande depuis l’onglet « Offres » d’une mutuelle.</p>
  @forelse ($requests as $r)
    <a class="mx-card mx-card__pad mx-row" href="{{ route('social.adhesion', $r) }}" style="color:inherit;gap:12px">
      <x-mx.avatar :src="$r->mutual?->profile?->logoUrl()" :name="$r->mutual?->name ?? '?'" org />
      <span class="mx-grow"><b>{{ $r->mutual?->name }}</b><span class="mx-xs mx-muted" style="display:block">{{ $r->reference }} · envoyée le {{ $r->created_at->format('d/m/Y') }}</span></span>
      <span class="mx-status mx-status--{{ $r->status }}">{{ $r->statusLabel() }}</span>
    </a>
  @empty
    @include('social.partials.empty', ['icon' => 'file', 'title' => 'Aucune demande', 'text' => 'Choisissez une mutuelle puis « Demander l’adhésion » dans l’onglet Offres.', 'action' => ['Voir les mutuelles', route('social.mutuals')]])
  @endforelse
</x-layouts.social>
