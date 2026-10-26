@php $central = rtrim(config('app.url'), '/'); $labels = ['open' => 'À traiter', 'resolved' => 'Traités', 'dismissed' => 'Classés']; @endphp
<x-layouts.network title="Modération">
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">Modération du réseau social</h1>
      <p class="mt-1 text-sm text-ink/60">Signalements envoyés par les membres. Masquer retire le contenu de l'affichage public sans le supprimer.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('network.moderation.pages') }}" class="rounded-md border border-ink/15 bg-white px-3 py-2 text-sm hover:border-pine">Pages créées par les membres</a>
      <a href="{{ route('network.moderation.accounts') }}" class="rounded-md border border-ink/15 bg-white px-3 py-2 text-sm hover:border-pine">Comptes suspendus</a>
      <a href="{{ route('network.moderation.hidden') }}" class="rounded-md border border-ink/15 bg-white px-3 py-2 text-sm hover:border-pine">Publications masquées ({{ $hidden }})</a>
    </div>
  </div>
  <div class="mt-6 flex gap-2 text-sm">
    @foreach ($labels as $k => $l)
      <a href="{{ route('network.moderation.index', ['statut' => $k]) }}" class="rounded-md px-3 py-1.5 {{ $status === $k ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ $l }} ({{ $counts[$k] ?? 0 }})</a>
    @endforeach
  </div>
  <div class="mt-4 space-y-3">
    @forelse ($reports as $r)
      @php
        $target = $r->comment ?: $r->post;
        $acc = $r->reportedAccount; $pg = $r->reportedMutual;
      @endphp
      <div class="rounded-lg border border-ink/10 bg-white p-4 text-sm">
        <div class="flex flex-wrap justify-between gap-2">
          <div><span class="rounded-full bg-clay/10 px-2 py-0.5 text-xs text-clay">{{ \App\Models\Social\Report::REASONS[$r->reason] ?? $r->reason }}</span>
            <span class="ml-2 text-ink/60">{{ $r->targetLabel() }} @if($acc) <b>{{ $acc->name }}</b>@elseif($pg) <b>{{ $pg->name }}</b>@else de <b>{{ $r->comment ? $r->comment->authorName() : ($r->post?->authorName() ?? '—') }}</b>@endif</span></div>
          <span class="text-xs text-ink/45">Signalé par {{ $r->reporter?->name ?? '—' }} · {{ $r->created_at->format('d/m/Y H:i') }}</span>
        </div>
        @if ($acc)
          <p class="mt-3 text-ink/80">Profil : <a class="text-pine hover:underline" href="{{ $central }}/u/{{ $acc->id }}" target="_blank" rel="noopener">{{ $acc->name }} ↗</a> {{ $acc->bio ? '— ' . \Illuminate\Support\Str::limit($acc->bio, 200) : '' }} @if($acc->suspended_at)<b class="text-clay">(suspendu)</b>@endif</p>
        @elseif ($pg)
          <p class="mt-3 text-ink/80">Page : <a class="text-pine hover:underline" href="{{ $central }}/mutuelles/{{ $pg->slug }}" target="_blank" rel="noopener">{{ $pg->name }} ↗</a> {{ $pg->profile?->owner_account_id ? '(créée par un membre)' : '(organisation du réseau)' }} @if($pg->status === 'suspended')<b class="text-clay">(suspendue)</b>@endif</p>
        @else
        <blockquote class="mt-3 border-l-4 border-ink/10 pl-3 whitespace-pre-line text-ink/80">{{ $target ? \Illuminate\Support\Str::limit($target->body, 600) : '(contenu supprimé)' }}</blockquote>
        @endif
        @if ($r->details)<p class="mt-2 text-xs text-ink/60">Précisions : {{ $r->details }}</p>@endif
        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs">
          @php $pid = $r->post_id ?? $r->comment?->post_id; @endphp
          @if ($pid)<a href="{{ $central }}/publications/{{ $pid }}" target="_blank" rel="noopener" class="text-pine hover:underline">Ouvrir ↗</a>@endif
          @if ($target) <span class="text-ink/45">Statut actuel : {{ $target->status }}</span> @endif
          @if ($r->status === 'open')
            @if ($acc && ! $acc->suspended_at)
              <form method="POST" action="{{ route('network.moderation.hide', $r) }}" onsubmit="return confirm('Suspendre ce compte ? Il ne pourra plus se connecter et ses publications seront masquées.')">@csrf<button class="rounded bg-clay px-3 py-1.5 text-white">Suspendre le compte</button></form>
            @elseif ($pg && ! $pg->profile?->owner_account_id)
              <span class="text-ink/50">Organisation gérée sur MX-CONNECT : à traiter depuis « Mutuelles ».</span>
            @elseif ($pg && $pg->status !== 'suspended')
              <form method="POST" action="{{ route('network.moderation.hide', $r) }}" onsubmit="return confirm('Suspendre cette page ? Elle ne sera plus visible.')">@csrf<button class="rounded bg-clay px-3 py-1.5 text-white">Suspendre la page</button></form>
            @elseif ($target && in_array($target->status, ['published', 'visible'], true))
              <form method="POST" action="{{ route('network.moderation.hide', $r) }}">@csrf<button class="rounded bg-clay px-3 py-1.5 text-white">Masquer le contenu</button></form>
            @endif
            <form method="POST" action="{{ route('network.moderation.dismiss', $r) }}">@csrf<button class="rounded border border-ink/15 px-3 py-1.5 hover:border-pine">Classer sans suite</button></form>
          @else
            <span class="text-ink/45">{{ $labels[$r->status] ?? $r->status }} le {{ $r->handled_at?->format('d/m/Y H:i') }}</span>
          @endif
        </div>
      </div>
    @empty
      <p class="rounded-lg border border-ink/10 bg-white px-4 py-10 text-center text-sm text-ink/45">Aucun signalement dans cette catégorie.</p>
    @endforelse
  </div>
  <div class="mt-4">{{ $reports->links() }}</div>
</x-layouts.network>
