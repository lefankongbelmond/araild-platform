<x-layouts.tenant :title="__('mxconnect.guarantee.plural')">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.guarantee.plural') }}</h1>
      <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.guarantee.sub') }}</p>
    </div>
    @can('manage-parameters')
    <a href="{{ route('guarantees.create') }}" class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">+ {{ __('mxconnect.guarantee.new') }}</a>
    @endcan
  </div>

  <div class="mt-6 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.guarantee.name') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.guarantee.contribution') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.guarantee.coverage') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.guarantee.observation') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.guarantee.version') }}</th>
        <th class="px-4 py-2"></th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($guarantees as $g)
          @php $v = $g->currentVersion; @endphp
          <tr>
            <td class="px-4 py-3 font-medium">{{ $g->name }}<div class="text-xs text-ink/40">{{ $g->code }}</div></td>
            <td class="px-4 py-3">{{ $v ? $currency->format($v->base_contribution_minor) : '—' }} <span class="text-xs text-ink/40">/ {{ $v?->periodicity }}</span></td>
            <td class="px-4 py-3">{{ $v?->coverage_rate }}% <span class="text-xs text-ink/40">({{ __('mxconnect.guarantee.copay') }} {{ $v?->copay_rate }}%)</span></td>
            <td class="px-4 py-3">{{ $v?->observation_days }} {{ __('mxconnect.guarantee.days') }}</td>
            <td class="px-4 py-3"><span class="rounded-full bg-mist px-2 py-0.5 text-xs">V{{ $v?->version_no }}</span></td>
            <td class="px-4 py-3 text-right"><a href="{{ route('guarantees.show', $g) }}" class="text-pine hover:underline">{{ __('mxconnect.guarantee.history') }}</a></td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.guarantee.empty') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-layouts.tenant>
