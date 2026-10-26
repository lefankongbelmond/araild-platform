@php $S = \App\Models\Social\AdhesionRequest::STATUSES; $col = ['submitted' => 'clay', 'in_review' => 'pine', 'info_requested' => 'sage', 'accepted' => 'pine', 'rejected' => 'ink/50', 'withdrawn' => 'ink/50']; @endphp
<x-layouts.tenant title="Adhésions en ligne">
  <x-tenant.hero icon="user-plus" kicker="Réseau social" tone="leaf">
    <x-slot:title>Demandes d’adhésion reçues en ligne</x-slot:title>
    <x-slot:subtitle>Dossiers envoyés depuis votre page publique MX-CONNECT. Données personnelles : à traiter uniquement pour l’instruction de la demande.</x-slot:subtitle>
  </x-tenant.hero>
  <div class="mt-6 flex flex-wrap gap-2 text-sm">
    <a href="{{ route('online-adhesions.index') }}" class="rounded-md px-3 py-1.5 {{ ! $status ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">À traiter ({{ collect(\App\Models\Social\AdhesionRequest::OPEN)->sum(fn ($k) => $counts[$k] ?? 0) }})</a>
    @foreach ($S as $k => $l)
      <a href="{{ route('online-adhesions.index', ['statut' => $k]) }}" class="rounded-md px-3 py-1.5 {{ $status === $k ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ $l }} ({{ $counts[$k] ?? 0 }})</a>
    @endforeach
  </div>
  <div class="mt-4 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr><th class="px-4 py-2 font-medium">Référence</th><th class="px-4 py-2 font-medium">Demandeur</th><th class="px-4 py-2 font-medium">Foyer</th><th class="px-4 py-2 font-medium">Reçue le</th><th class="px-4 py-2 font-medium">Statut</th><th></th></tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($items as $r)
          <tr>
            <td class="px-4 py-3 font-mono text-xs">{{ $r->reference }}</td>
            <td class="px-4 py-3"><b>{{ $r->name }}</b><div class="text-xs text-ink/50">{{ $r->city }} · {{ $r->phone }}</div></td>
            <td class="px-4 py-3">{{ $r->household_size }} pers. · {{ $r->documents_count }} pièce(s)</td>
            <td class="px-4 py-3 text-ink/60">{{ $r->created_at->format('d/m/Y H:i') }}</td>
            <td class="px-4 py-3"><span class="rounded-full border border-{{ $col[$r->status] ?? 'ink/40' }}/30 px-2 py-0.5 text-xs text-{{ $col[$r->status] ?? 'ink/40' }}">{{ $r->statusLabel() }}</span></td>
            <td class="px-4 py-3 text-right"><a class="text-pine hover:underline" href="{{ route('online-adhesions.show', $r->reference) }}">Ouvrir</a></td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-4 py-10 text-center text-ink/45">Aucune demande dans cette catégorie.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $items->links() }}</div>
</x-layouts.tenant>
