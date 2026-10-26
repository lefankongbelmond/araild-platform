<x-layouts.tenant :title="'Membres · ' . $group->name">
  <a href="{{ route('social-groups.index') }}" class="text-sm text-pine hover:underline">← Groupes</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">Membres de « {{ $group->name }} »</h1>
  @if ($pending->isNotEmpty())
    <h2 class="mt-6 font-semibold">Demandes en attente ({{ $pending->count() }})</h2>
    <div class="mt-2 divide-y divide-ink/5 overflow-hidden rounded-lg border border-ink/10 bg-white">
      @foreach ($pending as $m)
        <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
          <span><b>{{ $m->account?->name }}</b> <span class="text-xs text-ink/50">· demandé le {{ $m->created_at->format('d/m/Y') }}@if($m->account?->city) · {{ $m->account->city }}@endif</span></span>
          <span class="flex gap-2">
            <form method="POST" action="{{ route('social-groups.members.decide', [$group->id, $m->id]) }}">@csrf<input type="hidden" name="action" value="approve"><button class="rounded bg-pine px-3 py-1 text-white">Accepter</button></form>
            <form method="POST" action="{{ route('social-groups.members.decide', [$group->id, $m->id]) }}">@csrf<input type="hidden" name="action" value="reject"><button class="rounded border border-ink/15 px-3 py-1">Refuser</button></form>
          </span>
        </div>
      @endforeach
    </div>
  @endif
  <h2 class="mt-6 font-semibold">Membres ({{ $active->total() }})</h2>
  <div class="mt-2 divide-y divide-ink/5 overflow-hidden rounded-lg border border-ink/10 bg-white">
    @forelse ($active as $m)
      <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
        <span><b>{{ $m->account?->name }}</b> <span class="text-xs text-ink/50">· membre depuis le {{ $m->updated_at->format('d/m/Y') }}</span></span>
        <form method="POST" action="{{ route('social-groups.members.decide', [$group->id, $m->id]) }}" onsubmit="return confirm('Retirer ce membre du groupe ?')">@csrf<input type="hidden" name="action" value="remove"><button class="text-xs text-clay hover:underline">Retirer</button></form>
      </div>
    @empty
      <p class="px-4 py-8 text-center text-sm text-ink/45">Aucun membre.</p>
    @endforelse
  </div>
  <div class="mt-4">{{ $active->links() }}</div>
</x-layouts.tenant>
