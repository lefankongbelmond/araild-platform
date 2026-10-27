@php $F = \App\Models\Social\BetaFeedback::class; @endphp
<x-layouts.network title="Retours bêta">
  <div>
    <h1 class="text-2xl font-semibold tracking-tight">Retours des testeurs</h1>
    <p class="mt-1 text-sm text-ink/60">Messages envoyés depuis « Centre d’aide → Signaler un problème » de l’application et du site. Ils ne contiennent jamais de donnée médicale demandée par la plateforme ; supprimez toute information sensible avant de les partager.</p>
  </div>
  @if (session('success'))<div class="mt-4 rounded-md bg-pine/10 px-4 py-2 text-sm text-pine">{{ session('success') }}</div>@endif
  <div class="mt-6 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-xs uppercase text-ink/60"><tr><th class="px-4 py-3">Message</th><th class="px-4 py-3">Contexte</th><th class="px-4 py-3">Statut</th></tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($items as $f)
          <tr>
            <td class="px-4 py-3"><b>{{ $F::CATEGORIES[$f->category] ?? $f->category }}</b> — <span class="text-xs text-ink/45">{{ $f->created_at?->format('d/m/Y H:i') }}</span>
              <div class="mt-1 whitespace-pre-line">{{ $f->message }}</div></td>
            <td class="px-4 py-3 text-xs text-ink/60">
              <div>{{ $f->account?->name ?? 'Visiteur' }}@if ($f->contact) · {{ $f->contact }}@endif</div>
              @if ($f->screen)<div>Écran : {{ $f->screen }}</div>@endif
              @if ($f->app_version)<div>Application : version {{ $f->app_version }}</div>@endif
              @if ($f->device)<div>{{ $f->device }}</div>@endif
            </td>
            <td class="px-4 py-3">
              <form method="POST" action="{{ route('network.android.feedback.status', $f->id) }}" class="flex flex-wrap gap-2">@csrf
                <select name="status" class="rounded-md border border-ink/15 bg-white px-2 py-1 text-xs" aria-label="Statut">
                  @foreach ($F::STATUSES as $k => $v)<option value="{{ $k }}" @selected($f->status === $k)>{{ $v }}</option>@endforeach
                </select>
                <button class="rounded border border-ink/15 px-3 py-1.5 text-xs">OK</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="3" class="px-4 py-8 text-center text-ink/50">Aucun retour pour l’instant.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if (method_exists($items, 'links'))<div class="mt-4">{{ $items->links() }}</div>@endif
</x-layouts.network>
