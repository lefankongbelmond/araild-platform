<x-layouts.tenant :title="__('mxconnect.accounting.close_cash')">
  <a href="{{ route('treasury.dashboard') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.treasury.title') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('mxconnect.accounting.close_cash') }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.accounting.close_sub') }}</p>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    @if (auth()->user()?->hasRole('treasurer_accountant'))
    <form method="POST" action="{{ route('accounting.close') }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3 self-start">
      @csrf
      <div>
        <label class="block text-sm font-medium">{{ __('mxconnect.accounting.day') }}</label>
        <input name="day" type="date" value="{{ $today }}" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      </div>
      <div class="rounded-md bg-mist px-3 py-2 text-sm">
        {{ __('mxconnect.accounting.theoretical_today') }}: <b>{{ $currency->format($theoretical) }}</b>
      </div>
      <div>
        <label class="block text-sm font-medium">{{ __('mxconnect.accounting.actual_counted') }}</label>
        <input name="actual" type="number" step="any" min="0" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      </div>
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.accounting.do_close') }}</button>
    </form>
    @endif

    <div class="lg:col-span-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.accounting.day') }}</th>
          <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.accounting.theoretical') }}</th>
          <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.accounting.actual') }}</th>
          <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.accounting.discrepancy') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.status') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($recentDays as $d)
            @php $diff = ($d->actual_balance_minor ?? 0) - ($d->theoretical_balance_minor ?? 0); @endphp
            <tr>
              <td class="px-4 py-2">{{ $d->day?->format('d/m/Y') }}</td>
              <td class="px-4 py-2 text-right">{{ $currency->format($d->theoretical_balance_minor ?? 0) }}</td>
              <td class="px-4 py-2 text-right">{{ $currency->format($d->actual_balance_minor ?? 0) }}</td>
              <td class="px-4 py-2 text-right {{ $diff === 0 ? 'text-ink/40' : 'text-clay' }}">{{ $currency->format($diff) }}</td>
              <td class="px-4 py-2">{{ $d->status === 'closed' ? __('mxconnect.accounting.status_closed') : __('mxconnect.accounting.status_open') }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-ink/40">{{ __('mxconnect.accounting.no_days') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</x-layouts.tenant>
