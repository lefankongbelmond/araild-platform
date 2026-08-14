<x-layouts.tenant :title="__('mxconnect.solvency.title')">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.solvency.title') }}</h1>
      <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.solvency.sub') }}</p>
    </div>
    <form method="GET">
      <select name="year" onchange="this.form.submit()" class="rounded-md border-ink/20 bg-white px-3 py-2 text-sm">
        @for ($y = now()->year; $y >= now()->year - 4; $y--)<option value="{{ $y }}" @selected($year===$y)>{{ $y }}</option>@endfor
      </select>
    </form>
  </div>

  @php
    $bandTone = ['healthy'=>'text-sage','watch'=>'text-clay','critical'=>'text-clay','unknown'=>'text-ink/40'][$lossBand];
    $solvOk = $solvency !== null && $solvency >= $solvencyFloor;
  @endphp

  <div class="mt-6 grid gap-4 md:grid-cols-4">
    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <div class="text-sm text-ink/50">{{ __('mxconnect.solvency.loss_ratio') }}</div>
      <div class="mt-1 text-3xl font-semibold {{ $bandTone }}">{{ $lossRatio !== null ? number_format($lossRatio * 100, 1).'%' : '—' }}</div>
      <div class="mt-1 text-xs {{ $bandTone }}">{{ __('mxconnect.solvency.band_'.$lossBand) }}</div>
    </div>
    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <div class="text-sm text-ink/50">{{ __('mxconnect.solvency.premiums') }}</div>
      <div class="mt-1 text-xl font-semibold text-sage">{{ $currency->format($premiums) }}</div>
    </div>
    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <div class="text-sm text-ink/50">{{ __('mxconnect.solvency.claims') }}</div>
      <div class="mt-1 text-xl font-semibold text-clay">{{ $currency->format($claims) }}</div>
    </div>
    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <div class="text-sm text-ink/50">{{ __('mxconnect.solvency.technical_result') }}</div>
      <div class="mt-1 text-xl font-semibold {{ $technical >= 0 ? 'text-sage' : 'text-clay' }}">{{ $currency->format($technical) }}</div>
    </div>
  </div>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-lg border border-ink/10 bg-white p-4">
      <h2 class="font-medium">{{ __('mxconnect.solvency.monthly') }} {{ $year }}</h2>
      @php $max = max(1, collect($series)->flatMap(fn($s)=>[$s['premiums'],$s['claims']])->max()); @endphp
      <div class="mt-4 flex items-end gap-2 h-40">
        @foreach ($series as $s)
          <div class="flex-1 flex flex-col items-center gap-1">
            <div class="flex items-end gap-0.5 h-32">
              <div class="w-2.5 rounded-t bg-sage" style="height: {{ (int) round($s['premiums']/$max*100) }}%" title="{{ $currency->format($s['premiums']) }}"></div>
              <div class="w-2.5 rounded-t bg-clay" style="height: {{ (int) round($s['claims']/$max*100) }}%" title="{{ $currency->format($s['claims']) }}"></div>
            </div>
            <div class="text-[10px] text-ink/50">{{ str_pad($s['month'],2,'0',STR_PAD_LEFT) }}</div>
          </div>
        @endforeach
      </div>
      <div class="mt-2 flex gap-4 text-xs text-ink/50">
        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded bg-sage"></span>{{ __('mxconnect.solvency.premiums') }}</span>
        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded bg-clay"></span>{{ __('mxconnect.solvency.claims') }}</span>
      </div>
    </div>

    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <h2 class="font-medium">{{ __('mxconnect.solvency.solvency_ratio') }}</h2>
      <div class="mt-3 text-3xl font-semibold {{ $solvency === null ? 'text-ink/40' : ($solvOk ? 'text-sage' : 'text-clay') }}">
        {{ $solvency !== null ? number_format($solvency, 2) : '—' }}
      </div>
      <p class="mt-1 text-xs {{ $solvOk ? 'text-sage' : 'text-clay' }}">
        {{ $solvency === null ? __('mxconnect.solvency.no_data') : ($solvOk ? __('mxconnect.solvency.solvent') : __('mxconnect.solvency.under_floor', ['floor' => number_format($solvencyFloor,2)])) }}
      </p>
      <dl class="mt-4 space-y-1 text-sm">
        <div class="flex justify-between"><dt class="text-ink/60">{{ __('mxconnect.solvency.reserves') }}</dt><dd class="font-medium">{{ $currency->format($reserves) }}</dd></div>
        <div class="flex justify-between"><dt class="text-ink/60">{{ __('mxconnect.solvency.required') }}</dt><dd class="font-medium">{{ $currency->format($required) }}</dd></div>
      </dl>
      <p class="mt-3 text-xs text-ink/40">{{ __('mxconnect.solvency.reserve_note', ['n' => config('mxconnect.actuarial.reserve_months')]) }}</p>
    </div>
  </div>
</x-layouts.tenant>
