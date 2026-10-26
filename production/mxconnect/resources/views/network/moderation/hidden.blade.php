<x-layouts.network title="Publications masquées">
  <a href="{{ route('network.moderation.index') }}" class="text-sm text-pine hover:underline">← Modération</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">Publications masquées</h1>
  <div class="mt-6 divide-y divide-ink/5 relative min-w-0 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    @forelse ($posts as $p)
      <div class="flex items-start justify-between gap-4 px-4 py-3 text-sm">
        <div><b>{{ $p->authorName() }}</b> <span class="text-xs text-ink/45">masquée le {{ $p->updated_at->format('d/m/Y H:i') }}</span><p class="mt-1 whitespace-pre-line">{{ \Illuminate\Support\Str::limit($p->body, 300) }}</p></div>
        <form method="POST" action="{{ route('network.moderation.restore', $p) }}">@csrf<button class="rounded border border-ink/15 px-3 py-1.5 text-xs hover:border-pine">Réafficher</button></form>
      </div>
    @empty
      <p class="px-4 py-10 text-center text-sm text-ink/45">Aucune publication masquée.</p>
    @endforelse
  </div>
  <div class="mt-4">{{ $posts->links() }}</div>
</x-layouts.network>
