<x-layouts.social title="Messages" active="messages">
  <h1 style="font-size:22px">Messages</h1>
  <p class="mx-small mx-muted">Échanges privés avec les mutuelles. Pour écrire à une mutuelle, ouvrez sa page puis l’onglet « Contact ».</p>
  <section class="mx-card">
    @forelse ($conversations as $c)
      <a class="mx-notif @if($c->account_unread) is-unread @endif" href="{{ route('social.messages.show', $c) }}">
        <x-mx.avatar :src="$c->mutual?->profile?->logoUrl()" :name="$c->mutual?->name ?? '?'" org />
        <span class="mx-grow" style="min-width:0"><b>{{ $c->mutual?->name }}</b> @if($c->account_unread)<span class="mx-badge mx-badge--blue">{{ $c->account_unread }} nouveau(x)</span>@endif
          <span class="mx-small" style="display:block">{{ $c->subject }}</span>
          <span class="mx-xs mx-muted mx-truncate" style="display:block">{{ $c->lastMessage?->sender === 'mutual' ? '' : 'Vous : ' }}{{ \Illuminate\Support\Str::limit($c->lastMessage?->body, 90) }}</span></span>
        <span class="mx-xs mx-muted">{{ $c->last_message_at?->diffForHumans() }}</span>
      </a>
    @empty
      <p class="mx-card__pad mx-small mx-muted">Aucune conversation pour le moment.</p>
    @endforelse
  </section>
  {{ $conversations->links('social.partials.pagination') }}
</x-layouts.social>
