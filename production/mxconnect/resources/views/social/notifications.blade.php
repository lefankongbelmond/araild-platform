<x-layouts.social title="Notifications" active="notifications">
  <div class="mx-row mx-between"><h1 style="font-size:22px">Notifications</h1>
    <form method="POST" action="{{ route('social.notifications.read') }}">@csrf<button class="mx-btn mx-btn--ghost mx-btn--sm">Tout marquer comme lu</button></form></div>
  <section class="mx-card">
    @forelse ($items as $n)
      @php $icon = ['comment' => 'comment', 'reply' => 'comment', 'message' => 'message', 'adhesion' => 'file', 'mutuelle' => 'building', 'mention' => 'at', 'follow' => 'users'][$n->type] ?? 'bell'; @endphp
      <a class="mx-notif @if(! $n->read_at) is-unread @endif" href="{{ route('social.notifications.open', $n->id) }}">
        <span class="mx-avatar mx-avatar--sm" style="background:var(--mx-blue-soft);color:var(--mx-blue)"><x-mx.icon :name="$icon" :size="16"/></span>
        <span class="mx-grow"><b class="mx-small">{{ $n->title }}</b>@if($n->body)<span class="mx-xs mx-muted" style="display:block">{{ $n->body }}</span>@endif</span>
        <span class="mx-xs mx-muted">{{ $n->created_at->diffForHumans() }}</span>
      </a>
    @empty
      <p class="mx-card__pad mx-small mx-muted">Aucune notification.</p>
    @endforelse
  </section>
  {{ $items->links('social.partials.pagination') }}
</x-layouts.social>
