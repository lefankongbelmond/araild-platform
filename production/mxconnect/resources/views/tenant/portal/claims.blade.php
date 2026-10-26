@php $S = ['captured' => 'En attente de validation', 'validated' => 'Validée', 'rejected' => 'Rejetée', 'controlled' => 'Contrôlée']; @endphp
<x-layouts.tenant title="Mes prises en charge">
  @include('tenant.portal._head', ['title' => 'Mes prises en charge'])

  <div class="mt-6 flex flex-wrap gap-2 text-sm">
    <a href="{{ route('portal.claims') }}" class="rounded-md px-3 py-1.5 {{ ! $status ? 'bg-pine text-white' : 'border border-ink/10 bg-white text-ink/70' }}">Toutes</a>
    @foreach (['captured', 'validated', 'rejected'] as $k)<a href="{{ route('portal.claims', ['statut' => $k]) }}" class="rounded-md px-3 py-1.5 {{ $status === $k ? 'bg-pine text-white' : 'border border-ink/10 bg-white text-ink/70' }}">{{ $S[$k] }} ({{ $counts[$k] ?? 0 }})</a>@endforeach
  </div>

  <div class="mt-4 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">N°</th><th class="px-4 py-2 font-medium">Patient</th><th class="px-4 py-2 font-medium">Acte</th>
        <th class="px-4 py-2 font-medium">Total</th><th class="px-4 py-2 font-medium">Part mutuelle</th><th class="px-4 py-2 font-medium">Part patient</th><th class="px-4 py-2 font-medium">État</th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($items as $p)
          @php $claim = $p->careClaim; $who = $claim?->dependent ?? $claim?->member; @endphp
          <tr>
            <td class="px-4 py-3 text-ink/60">{{ $p->id }}<div class="text-xs">{{ $p->care_date?->format('d/m/Y') }}</div></td>
            <td class="px-4 py-3">{{ $who?->last_name }} {{ $who?->first_name }}@if($claim?->dependent)<div class="text-xs text-ink/50">à charge de {{ $claim->member?->last_name }} {{ $claim->member?->first_name }}</div>@endif</td>
            <td class="px-4 py-3">{{ $p->actType?->label }}</td>
            <td class="px-4 py-3 tabular-nums">{{ $currency->format($p->total_minor) }}</td>
            <td class="px-4 py-3 font-medium tabular-nums">{{ $currency->format($p->mutual_part_minor) }}</td>
            <td class="px-4 py-3 tabular-nums">{{ $currency->format($p->beneficiary_part_minor) }}</td>
            <td class="px-4 py-3 text-xs {{ $p->status === 'validated' ? 'text-pine' : ($p->status === 'rejected' ? 'text-clay' : 'text-ink/60') }}">{{ $S[$p->status] ?? $p->status }}</td>
          </tr>
        @empty
          <tr><td colspan="7" class="px-4 py-10 text-center text-ink/45">Aucune prise en charge. Commencez par <a class="text-pine underline" href="{{ route('portal.index') }}">vérifier les droits</a> d’un adhérent.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $items->links() }}</div>
</x-layouts.tenant>
