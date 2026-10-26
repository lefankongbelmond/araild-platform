@php $central = rtrim(config('app.url'), '/'); @endphp
<x-layouts.network title="Comptes suspendus">
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div><h1 class="text-2xl font-semibold tracking-tight">Comptes suspendus</h1>
      <p class="mt-1 text-sm text-ink/60">Un compte suspendu ne peut plus se connecter ; ses publications et commentaires ne sont plus affichés. Rien n'est supprimé.</p></div>
    <a href="{{ route('network.moderation.index') }}" class="rounded-md border border-ink/15 bg-white px-3 py-2 text-sm hover:border-pine">← Signalements</a>
  </div>
  @if (session('success'))<div class="mt-4 rounded-md bg-pine/10 px-4 py-2 text-sm text-pine">{{ session('success') }}</div>@endif
  <div class="mt-6 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-xs uppercase text-ink/60"><tr><th class="px-4 py-3">Compte</th><th class="px-4 py-3">Motif</th><th class="px-4 py-3">Suspendu le</th><th class="px-4 py-3"></th></tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($accounts as $a)
          <tr><td class="px-4 py-3"><a class="text-pine hover:underline" href="{{ $central }}/u/{{ $a->id }}" target="_blank" rel="noopener">{{ $a->name }}</a></td><td class="px-4 py-3">{{ $a->suspension_reason ?? '—' }}</td><td class="px-4 py-3">{{ $a->suspended_at?->format('d/m/Y H:i') }}</td>
            <td class="px-4 py-3 text-right"><form method="POST" action="{{ route('network.moderation.accounts.restore', $a->id) }}">@csrf<button class="rounded border border-ink/15 px-3 py-1.5 text-xs hover:border-pine">Réactiver</button></form></td></tr>
        @empty
          <tr><td colspan="4" class="px-4 py-8 text-center text-ink/50">Aucun compte suspendu.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $accounts->links() }}</div>
</x-layouts.network>
