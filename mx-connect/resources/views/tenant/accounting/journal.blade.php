<x-layouts.tenant :title="__('mxconnect.accounting.journal')">
  <div class="flex items-center justify-between">
    <a href="{{ route('treasury.dashboard') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.treasury.title') }}</a>
    <a href="{{ route('accounting.chart') }}" class="text-sm text-pine hover:underline">{{ __('mxconnect.accounting.chart') }}</a>
  </div>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('mxconnect.accounting.journal') }}</h1>

  <div class="mt-6 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.accounting.date') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.accounting.piece') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.accounting.debit') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.accounting.credit') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.accounting.label') }}</th>
        <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.accounting.amount') }}</th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($entries as $e)
          <tr>
            <td class="px-4 py-2">{{ $e->accountingDay?->day?->format('d/m/Y') }}</td>
            <td class="px-4 py-2 font-mono text-xs">{{ $e->piece }}</td>
            <td class="px-4 py-2 font-mono">{{ $e->debitAccount?->number }}</td>
            <td class="px-4 py-2 font-mono">{{ $e->creditAccount?->number }}</td>
            <td class="px-4 py-2 text-ink/60">{{ $e->label }}</td>
            <td class="px-4 py-2 text-right font-medium">{{ $currency->format($e->amount_minor) }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.accounting.journal_empty') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts.tenant>
