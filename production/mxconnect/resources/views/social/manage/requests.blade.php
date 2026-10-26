@php $R = \App\Models\Social\SocialRequest::class; @endphp
<x-layouts.social :title="'Demandes reçues · ' . $page->name" active="pages">
  @include('social.manage._head')
  <div class="mx-chips" aria-label="Type de demande">
    <a href="{{ route('social.manage.requests', array_filter([$page->slug, 'statut' => $status])) }}" @class(['mx-chip', 'is-active' => ! $kind])>Toutes</a>
    @foreach ($R::KINDS as $k => $v)<a href="{{ route('social.manage.requests', array_filter([$page->slug, 'type' => $k, 'statut' => $status])) }}" @class(['mx-chip', 'is-active' => $kind === $k])>{{ $v }}</a>@endforeach
  </div>
  <div class="mx-chips" aria-label="Statut">
    <a href="{{ route('social.manage.requests', array_filter([$page->slug, 'type' => $kind])) }}" @class(['mx-chip', 'is-active' => ! $status])>À traiter et acceptées</a>
    @foreach ($R::STATUSES as $k => $v)<a href="{{ route('social.manage.requests', array_filter([$page->slug, 'type' => $kind, 'statut' => $k])) }}" @class(['mx-chip', 'is-active' => $status === $k])>{{ $v }}</a>@endforeach
  </div>
  @forelse ($items as $r)
    <a class="mx-card mx-card__pad mx-row" href="{{ route('social.manage.request', [$page->slug, $r->reference]) }}" style="color:inherit;gap:12px">
      <span class="mx-avatar mx-avatar--sm" style="background:var(--mx-blue-soft);color:var(--mx-blue)"><x-mx.icon :name="$r->kind === 'application' ? 'briefcase' : ($r->kind === 'support' ? 'handshake' : 'hand')" :size="16"/></span>
      <span class="mx-grow" style="min-width:0"><b class="mx-truncate" style="display:block">{{ $r->contact_name }} · {{ $r->kindLabel() }}</b>
        <span class="mx-xs mx-muted" style="display:block">{{ $r->subject() }} · {{ $r->reference }} · {{ $r->created_at->format('d/m/Y') }}</span></span>
      <span class="mx-status mx-status--{{ $r->statusClass() }}">{{ $r->statusLabel() }}</span>
    </a>
  @empty
    @include('social.partials.empty', ['icon' => 'handshake', 'title' => 'Aucune demande', 'text' => 'Les candidatures et propositions d’engagement ou de soutien reçues par la page apparaîtront ici.'])
  @endforelse
  {{ $items->links('social.partials.pagination') }}
  <p class="mx-xs mx-muted" style="padding:0 6px">Données personnelles : utilisez-les seulement pour répondre à la demande. Visibles par le créateur et les administrateurs de la page.</p>
</x-layouts.social>
