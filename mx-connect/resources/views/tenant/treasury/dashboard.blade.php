<x-layouts.tenant :title="__('mxconnect.treasury.title')">
  <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.treasury.title') }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.treasury.sub') }}</p>

  <div class="mt-6 grid gap-4 md:grid-cols-4">
    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <div class="text-sm text-ink/50">{{ __('mxconnect.treasury.balance') }}</div>
      <div class="mt-1 text-2xl font-semibold text-pine">{{ $currency->format($balance) }}</div>
    </div>
    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <div class="text-sm text-ink/50">{{ __('mxconnect.treasury.inflow') }}</div>
      <div class="mt-1 text-xl font-semibold text-sage">+{{ $currency->format($inflow) }}</div>
    </div>
    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <div class="text-sm text-ink/50">{{ __('mxconnect.treasury.outflow') }}</div>
      <div class="mt-1 text-xl font-semibold text-clay">−{{ $currency->format($outflow) }}</div>
    </div>
    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <div class="text-sm text-ink/50">{{ __('mxconnect.treasury.theoretical_cash') }}</div>
      <div class="mt-1 text-xl font-semibold">{{ $currency->format($theoretical) }}</div>
    </div>
  </div>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-lg border border-ink/10 bg-white p-4">
      <h2 class="font-medium">{{ __('mxconnect.treasury.six_months') }}</h2>
      {{-- Lightweight inline bar chart (no external deps) --}}
      @php $max = max(1, collect($series)->flatMap(fn($s)=>[$s['inflow'],$s['outflow']])->max()); @endphp
      <div class="mt-4 flex items-end gap-3 h-40">
        @foreach ($series as $s)
          <div class="flex-1 flex flex-col items-center gap-1">
            <div class="flex items-end gap-0.5 h-32">
              <div class="w-3 rounded-t bg-sage" style="height: {{ (int) round($s['inflow']/$max*100) }}%" title="+{{ $currency->format($s['inflow']) }}"></div>
              <div class="w-3 rounded-t bg-clay" style="height: {{ (int) round($s['outflow']/$max*100) }}%" title="−{{ $currency->format($s['outflow']) }}"></div>
            </div>
            <div class="text-[10px] text-ink/50">{{ substr($s['label'], 2) }}</div>
          </div>
        @endforeach
      </div>
      <div class="mt-2 flex gap-4 text-xs text-ink/50"><span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded bg-sage"></span>{{ __('mxconnect.treasury.inflow') }}</span><span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded bg-clay"></span>{{ __('mxconnect.treasury.outflow') }}</span></div>
    </div>

    <div class="space-y-4">
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <h2 class="font-medium">{{ __('mxconnect.treasury.by_mode') }}</h2>
        <dl class="mt-2 space-y-1 text-sm">
          @foreach ($byMode as $mode => $amt)
            <div class="flex justify-between"><dt class="text-ink/60">{{ __('mxconnect.treasury.mode_'.$mode) }}</dt><dd class="font-medium">{{ $currency->format($amt) }}</dd></div>
          @endforeach
        </dl>
      </div>
      @if ($unposted > 0)
      <div class="rounded-lg border border-clay/30 bg-clay/5 p-4">
        <p class="text-sm text-clay">{{ __('mxconnect.treasury.unposted', ['n' => $unposted]) }}</p>
        @if (auth()->user()?->hasAnyRole(['treasurer_accountant','mutual_admin']))
        <form method="POST" action="{{ route('accounting.post-treasury') }}" class="mt-2">@csrf
          <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white">{{ __('mxconnect.accounting.post_now') }}</button>
        </form>
        @endif
      </div>
      @endif
      <div class="flex gap-2">
        <a href="{{ route('treasury.movements') }}" class="flex-1 rounded-md border border-ink/20 px-3 py-2 text-center text-sm hover:bg-ink/5">{{ __('mxconnect.treasury.movements') }}</a>
        <a href="{{ route('accounting.close.form') }}" class="flex-1 rounded-md border border-ink/20 px-3 py-2 text-center text-sm hover:bg-ink/5">{{ __('mxconnect.accounting.close_cash') }}</a>
      </div>
    </div>
  </div>
</x-layouts.tenant>
