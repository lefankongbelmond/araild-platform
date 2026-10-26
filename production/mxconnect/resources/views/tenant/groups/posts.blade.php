<x-layouts.tenant :title="'Publications · ' . $group->name">
  <a href="{{ route('social-groups.index') }}" class="text-sm text-pine hover:underline">← Groupes</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">Publications de « {{ $group->name }} »</h1>
  <p class="mt-1 text-sm text-ink/60">Masquer retire la publication de l’affichage sans la supprimer.</p>
  <div class="mt-6 divide-y divide-ink/5 overflow-hidden rounded-lg border border-ink/10 bg-white">
    @forelse ($posts as $p)
      <div class="flex items-start justify-between gap-4 px-4 py-3 text-sm {{ $p->status === 'hidden' ? 'bg-clay/5' : '' }}">
        <div class="min-w-0"><b>{{ $p->authorName() }}</b> <span class="text-xs text-ink/50">· {{ $p->published_at?->format('d/m/Y H:i') }}@if($p->status === 'hidden') · masquée @endif</span>
          <p class="mt-1 whitespace-pre-line">{{ \Illuminate\Support\Str::limit($p->body, 400) }}</p>
          @if ($p->media->isNotEmpty())<p class="mt-1 text-xs text-ink/50">{{ $p->media->count() }} image(s)</p>@endif</div>
        <div class="flex flex-col items-end gap-1 text-xs whitespace-nowrap">
          <a class="text-pine hover:underline" href="{{ route('social-presence.comments', $p->id) }}">{{ $p->comments_count }} commentaire(s)</a>
          <form method="POST" action="{{ route('social-groups.posts.toggle', [$group->id, $p->id]) }}">@csrf<button class="{{ $p->status === 'hidden' ? 'text-pine' : 'text-clay' }} hover:underline">{{ $p->status === 'hidden' ? 'Réafficher' : 'Masquer' }}</button></form>
        </div>
      </div>
    @empty
      <p class="px-4 py-8 text-center text-sm text-ink/45">Aucune publication dans ce groupe.</p>
    @endforelse
  </div>
  <div class="mt-4">{{ $posts->links() }}</div>
</x-layouts.tenant>
