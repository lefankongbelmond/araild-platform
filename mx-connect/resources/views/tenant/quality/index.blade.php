<x-layouts.tenant :title="__('mxconnect.quality.title')">
  <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.quality.title') }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.quality.sub') }}</p>

  @php $tone = $score >= 80 ? 'text-sage' : ($score >= 50 ? 'text-clay' : 'text-clay'); @endphp
  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="rounded-lg border border-ink/10 bg-white p-6 text-center self-start">
      <div class="text-sm text-ink/50">{{ __('mxconnect.quality.score') }}</div>
      <div class="mt-2 text-5xl font-semibold {{ $tone }}">{{ $score }}<span class="text-2xl text-ink/30">/100</span></div>
      <div class="mt-4 h-2 rounded-full bg-mist overflow-hidden">
        <div class="h-full {{ $score >= 80 ? 'bg-sage' : 'bg-clay' }}" style="width: {{ $score }}%"></div>
      </div>
    </div>

    <div class="lg:col-span-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.quality.check') }}</th>
          <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.quality.passed') }}</th>
          <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.quality.rate') }}</th>
          <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.quality.weight') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @foreach ($checks as $c)
            <tr>
              <td class="px-4 py-2">{{ __('mxconnect.quality.chk_'.$c['key']) }}</td>
              <td class="px-4 py-2 text-right text-ink/60">{{ $c['total'] > 0 ? $c['passed'].' / '.$c['total'] : '—' }}</td>
              <td class="px-4 py-2 text-right font-medium {{ $c['total']===0 ? 'text-ink/30' : ($c['rate'] >= 0.8 ? 'text-sage' : 'text-clay') }}">
                {{ $c['total'] > 0 ? number_format($c['rate']*100,0).'%' : 'n/a' }}
              </td>
              <td class="px-4 py-2 text-right text-ink/40">×{{ $c['weight'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</x-layouts.tenant>
