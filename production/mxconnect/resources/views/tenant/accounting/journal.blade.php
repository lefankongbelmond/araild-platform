@php
  $K = \App\Models\Tenant\AccountingEntry::KINDS;
  $S = \App\Models\Tenant\AccountingEntry::STATUSES;
  $f = 'rounded-md border border-ink/15 bg-white px-3 py-2 text-sm';
@endphp
<x-layouts.tenant :title="__('mxconnect.accounting.journal')">
  @include('tenant.accounting._nav')
  <x-tenant.hero icon="book" kicker="Comptabilité" tone="violet">
    <x-slot:title>{{ __('mxconnect.accounting.journal') }}</x-slot:title>
    <x-slot:subtitle>Toutes les écritures, des plus récentes aux plus anciennes. Une écriture validée ne se modifie pas : elle se corrige par une extourne, validée par une autre personne.</x-slot:subtitle>
  </x-tenant.hero>

  <div class="relative mt-6 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.accounting.date') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.accounting.piece') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.accounting.debit') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.accounting.credit') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.accounting.label') }}</th>
        <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.accounting.amount') }}</th>
        <th class="px-4 py-2 font-medium">Origine</th>
        @if ($canReverse)<th class="px-4 py-2 font-medium"><span class="sr-only">Actions</span></th>@endif
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($entries as $e)
          @php $kind = $e->kind ?? 'auto'; @endphp
          <tr>
            <td class="px-4 py-2 whitespace-nowrap">{{ $e->accountingDay?->day?->format('d/m/Y') }}</td>
            <td class="px-4 py-2 font-mono text-xs">{{ $e->piece }}</td>
            <td class="px-4 py-2 font-mono" title="{{ $e->debitAccount?->label }}">{{ $e->debitAccount?->number }}</td>
            <td class="px-4 py-2 font-mono" title="{{ $e->creditAccount?->label }}">{{ $e->creditAccount?->number }}</td>
            <td class="px-4 py-2 text-ink/60">{{ $e->label }}</td>
            <td class="px-4 py-2 text-right font-medium whitespace-nowrap">{{ $currency->format($e->amount_minor) }}</td>
            <td class="px-4 py-2 text-xs whitespace-nowrap">{{ $K[$kind] ?? $kind }}
              @if ($e->status !== 'validated' && $e->status !== 'posted')
                <span class="ml-1 rounded-full px-2 py-0.5 {{ $e->status === 'rejected' ? 'bg-clay/10 text-clay' : 'bg-amber-50 text-amber-700' }}">{{ $S[$e->status] ?? $e->status }}</span>
              @elseif (in_array($e->id, $reversed, true))
                <span class="ml-1 rounded-full bg-mist px-2 py-0.5 text-ink/60">Extournée</span>
              @endif
            </td>
            @if ($canReverse)
              <td class="px-4 py-2">
                @if (in_array($e->status, ['validated', 'posted'], true) && ! in_array($kind, ['closing', 'reversal'], true) && ! in_array($e->id, $reversed, true))
                  <details>
                    <summary class="cursor-pointer text-xs text-pine">Extourner</summary>
                    <form method="POST" action="{{ route('accounting.entries.reverse', $e) }}" class="mt-2 flex flex-wrap items-end gap-2">@csrf
                      <label class="text-xs text-ink/60">Date de l’extourne<input type="date" name="date" value="{{ now()->toDateString() }}" class="{{ $f }} mt-1 block"></label>
                      <button class="rounded-md border border-ink/15 bg-white px-3 py-2 text-xs font-medium">Préparer l’extourne</button>
                    </form>
                  </details>
                @endif
              </td>
            @endif
          </tr>
        @empty
          <tr><td colspan="{{ $canReverse ? 8 : 7 }}" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.accounting.journal_empty') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts.tenant>
