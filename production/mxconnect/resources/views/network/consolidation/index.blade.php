<x-layouts.network :title="__('mxconnect.consolidation.title')">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.consolidation.title') }}</h1>
      <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.consolidation.sub') }}</p>
    </div>
    <div class="flex items-center gap-2">
      <form method="GET">
        <input type="hidden" name="refresh" value="1">
        <input type="hidden" name="year" value="{{ $year }}">
        <button class="rounded-md border border-ink/20 px-3 py-2 text-sm hover:bg-ink/5">↻ {{ __('mxconnect.consolidation.refresh') }}</button>
      </form>
      <form method="GET">
        <select name="year" onchange="this.form.submit()" class="rounded-md border-ink/20 bg-white px-3 py-2 text-sm">
          @for ($y = now()->year; $y >= now()->year - 4; $y--)<option value="{{ $y }}" @selected($year===$y)>{{ $y }}</option>@endfor
        </select>
      </form>
    </div>
  </div>

  {{-- Network-wide counts --}}
  <div class="mt-6 grid gap-4 md:grid-cols-4">
    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <div class="text-sm text-ink/50">{{ __('mxconnect.consolidation.mutuals') }}</div>
      <div class="mt-1 text-2xl font-semibold text-pine">{{ $totals['reachable'] }}<span class="text-base text-ink/30"> / {{ $totals['mutuals'] }}</span></div>
    </div>
    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <div class="text-sm text-ink/50">{{ __('mxconnect.consolidation.members') }}</div>
      <div class="mt-1 text-2xl font-semibold">{{ number_format($totals['members'], 0, ',', ' ') }}</div>
    </div>
    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <div class="text-sm text-ink/50">{{ __('mxconnect.consolidation.active_subs') }}</div>
      <div class="mt-1 text-2xl font-semibold">{{ number_format($totals['active_subscriptions'], 0, ',', ' ') }}</div>
    </div>
    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <div class="text-sm text-ink/50">{{ __('mxconnect.consolidation.currencies') }}</div>
      <div class="mt-1 text-2xl font-semibold">{{ count($totals['by_currency']) }}</div>
    </div>
  </div>

  {{-- Financial totals per currency --}}
  <h2 class="mt-8 font-medium">{{ __('mxconnect.consolidation.by_currency') }}</h2>
  <div class="mt-2 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
    @foreach ($totals['by_currency'] as $ccy => $b)
      @php $cur = $currencies[$ccy] ?? null; $band = $b['loss_ratio'] === null ? 'text-ink/40' : ($b['loss_ratio'] < 0.75 ? 'text-sage' : ($b['loss_ratio'] <= 1 ? 'text-clay' : 'text-clay')); @endphp
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <div class="flex items-center justify-between"><h3 class="font-medium">{{ $ccy }}</h3>
          <span class="text-xs {{ $band }}">S/P {{ $b['loss_ratio'] !== null ? number_format($b['loss_ratio']*100,1).'%' : '—' }}</span></div>
        <dl class="mt-2 space-y-1 text-sm">
          <div class="flex justify-between"><dt class="text-ink/50">{{ __('mxconnect.consolidation.premiums') }}</dt><dd class="font-medium text-sage">{{ $cur ? $cur->format($b['premiums']) : $b['premiums'] }}</dd></div>
          <div class="flex justify-between"><dt class="text-ink/50">{{ __('mxconnect.consolidation.claims') }}</dt><dd class="font-medium text-clay">{{ $cur ? $cur->format($b['claims']) : $b['claims'] }}</dd></div>
          <div class="flex justify-between"><dt class="text-ink/50">{{ __('mxconnect.consolidation.reserves') }}</dt><dd class="font-medium">{{ $cur ? $cur->format($b['reserves']) : $b['reserves'] }}</dd></div>
        </dl>
      </div>
    @endforeach
  </div>

  {{-- Per-mutual breakdown --}}
  <h2 class="mt-8 font-medium">{{ __('mxconnect.consolidation.per_mutual') }}</h2>
  <div class="mt-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.consolidation.mutual') }}</th>
        <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.consolidation.members') }}</th>
        <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.consolidation.premiums') }}</th>
        <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.consolidation.claims') }}</th>
        <th class="px-4 py-2 font-medium text-right">S/P</th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($rows as $r)
          @php $cur = $currencies[$r['currency']] ?? null; @endphp
          <tr class="{{ $r['reachable'] ? '' : 'opacity-50' }}">
            <td class="px-4 py-2">{{ $r['name'] }}<div class="text-xs text-ink/40">{{ $r['currency'] }}{{ $r['reachable'] ? '' : ' · '.__('mxconnect.health.down') }}</div></td>
            <td class="px-4 py-2 text-right">{{ $r['reachable'] ? number_format($r['members'],0,',',' ') : '—' }}</td>
            <td class="px-4 py-2 text-right">{{ $r['reachable'] ? ($cur ? $cur->format($r['premiums']) : $r['premiums']) : '—' }}</td>
            <td class="px-4 py-2 text-right">{{ $r['reachable'] ? ($cur ? $cur->format($r['claims']) : $r['claims']) : '—' }}</td>
            <td class="px-4 py-2 text-right">{{ $r['loss_ratio'] !== null ? number_format($r['loss_ratio']*100,1).'%' : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.consolidation.empty') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <p class="mt-3 text-xs text-ink/40">{{ __('mxconnect.consolidation.note') }}</p>
</x-layouts.network>
