@php $S = array_map('__', \App\Models\Tenant\Preauthorization::STATUSES); @endphp
<x-layouts.tenant title="{{ __('Accords préalables') }}">
  @include('tenant.portal._head', ['title' => 'Accords préalables'])
  <p class="mt-4 text-sm text-ink/60">{{ __('Pour demander un accord, vérifiez d’abord les droits de l’adhérent, puis cliquez sur « Demander un accord préalable ». Un accord accordé porte un numéro et ne sert qu’une fois, pour votre établissement.') }}</p>

  <div class="mt-4 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('Demande') }}</th><th class="px-4 py-2 font-medium">{{ __('Patient') }}</th><th class="px-4 py-2 font-medium">{{ __('Acte') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('Montant prévu') }}</th><th class="px-4 py-2 font-medium">{{ __('Décision') }}</th><th></th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($items as $p)
          @php $who = $p->dependent ?? $p->member; @endphp
          <tr class="align-top">
            <td class="px-4 py-3 text-ink/60">{{ __('n° :id', ['id' => $p->id]) }}<div class="text-xs">{{ __('prévu le :date', ['date' => $p->planned_on?->format('d/m/Y')]) }}</div>@if ($p->emergency)<div class="text-xs font-semibold text-amber-700">{{ __('Urgence (régularisation)') }}</div>@endif</td>
            <td class="px-4 py-3">{{ $who?->last_name }} {{ $who?->first_name }}</td>
            <td class="px-4 py-3">{{ $p->actType?->label }}</td>
            <td class="px-4 py-3 tabular-nums">{{ $currency->format($p->estimated_minor) }}</td>
            <td class="px-4 py-3 text-xs">
              <b class="{{ in_array($p->status, ['approved', 'used'], true) ? 'text-pine' : ($p->status === 'rejected' ? 'text-clay' : 'text-ink/70') }}">{{ $S[$p->status] ?? $p->status }}</b>
              @if ($p->number)<div class="font-mono">{{ $p->number }}</div>@endif
              @if ($p->approved_minor !== null)<div>{{ __('Part mutuelle accordée au plus : :amount', ['amount' => $currency->format($p->approved_minor)]) }}</div>@endif
              @if ($p->valid_until && $p->status === 'approved')<div>{{ __('Valable jusqu’au :date', ['date' => $p->valid_until->format('d/m/Y')]) }}</div>@endif
              @if ($p->decision_note)<div class="mt-1 {{ $p->status === 'rejected' ? 'text-clay' : 'text-ink/60' }}">{{ $p->status === 'rejected' ? 'Motif : ' : '' }}{{ $p->decision_note }}</div>@endif
            </td>
            <td class="px-4 py-3 text-right">@if ($p->status === 'requested' && ! $p->emergency)
              <form method="POST" action="{{ route('portal.preauth.cancel', $p->id) }}" onsubmit="return confirm('Retirer cette demande ?')">@csrf<button class="text-xs font-semibold text-clay hover:underline">{{ __('Retirer') }}</button></form>@endif</td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-4 py-10 text-center text-ink/45">{{ __('Aucune demande d’accord préalable.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $items->links() }}</div>
</x-layouts.tenant>
