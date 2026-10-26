<x-layouts.tenant title="Commentaires">
  <a href="{{ route('social-presence.index') }}" class="text-sm text-pine hover:underline">← Réseau social</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">Commentaires</h1>
  <p class="mt-1 text-sm text-ink/60">{{ \Illuminate\Support\Str::limit($post->body, 160) }}</p>
  <div class="mt-6 divide-y divide-ink/5 overflow-hidden rounded-lg border border-ink/10 bg-white">
    @forelse ($comments as $c)
      <div class="flex items-start justify-between gap-4 px-4 py-3 text-sm">
        <div><b>{{ $c->authorName() }}</b> <span class="text-xs text-ink/45">{{ $c->created_at->format('d/m/Y H:i') }}</span><p class="mt-1 whitespace-pre-line">{{ $c->body }}</p></div>
        <form method="POST" action="{{ route('social-presence.comments.hide', $c->id) }}" onsubmit="return confirm('Masquer ce commentaire ?')">@csrf<button class="text-xs text-clay hover:underline">Masquer</button></form>
      </div>
    @empty
      <p class="px-4 py-10 text-center text-sm text-ink/45">Aucun commentaire visible.</p>
    @endforelse
  </div>
  <div class="mt-4">{{ $comments->links() }}</div>
</x-layouts.tenant>
