@php $central = rtrim(config('app.url'), '/'); @endphp
<x-layouts.network title="Pages des membres">
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">Pages créées par les membres</h1>
      <p class="mt-1 text-sm text-ink/60">Pages d'organisations ouvertes par des comptes du réseau social (sans espace de gestion). Une page suspendue n'est plus visible du public.</p>
    </div>
    <a href="{{ route('network.moderation.index') }}" class="rounded-md border border-ink/15 bg-white px-3 py-2 text-sm hover:border-pine">← Signalements</a>
  </div>
  @if (session('success'))<div class="mt-4 rounded-md bg-pine/10 px-4 py-2 text-sm text-pine">{{ session('success') }}</div>@endif
  <div class="mt-6 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-xs uppercase text-ink/60"><tr><th class="px-4 py-3">Page</th><th class="px-4 py-3">Catégorie</th><th class="px-4 py-3">Créée par</th><th class="px-4 py-3">Abonnés</th><th class="px-4 py-3">Statut</th><th class="px-4 py-3"></th></tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($pages as $p)
          <tr>
            <td class="px-4 py-3"><a class="font-medium text-pine hover:underline" href="{{ $central }}/mutuelles/{{ $p->slug }}" target="_blank" rel="noopener">{{ $p->name }} ↗</a><div class="text-xs text-ink/45">{{ $p->city }}{{ $p->country ? ' · ' . $p->country->name : '' }} · créée le {{ $p->created_at?->format('d/m/Y') }}</div></td>
            <td class="px-4 py-3">{{ \App\Models\Social\MutualProfile::CATEGORIES[$p->profile?->category] ?? '—' }}</td>
            <td class="px-4 py-3">{{ $p->profile?->owner?->name ?? '—' }}</td>
            <td class="px-4 py-3">{{ (int) $p->profile?->followers_count }}</td>
            <td class="px-4 py-3">{{ $p->status === 'suspended' ? 'Suspendue' : 'Visible' }}</td>
            <td class="px-4 py-3 text-right"><form method="POST" action="{{ route('network.moderation.pages.toggle', $p->id) }}">@csrf<button class="rounded px-3 py-1.5 text-xs {{ $p->status === 'suspended' ? 'border border-ink/15' : 'bg-clay text-white' }}">{{ $p->status === 'suspended' ? 'Réactiver' : 'Suspendre' }}</button></form></td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-4 py-8 text-center text-ink/50">Aucune page créée par les membres.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $pages->links() }}</div>
</x-layouts.network>
