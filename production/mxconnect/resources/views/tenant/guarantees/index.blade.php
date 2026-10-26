<x-layouts.tenant :title="__('mxconnect.guarantee.plural')">
  <x-tenant.hero icon="heart" kicker="Paramètres · Garanties" tone="red">
    <x-slot:title>{{ __('mxconnect.guarantee.plural') }}</x-slot:title>
    <x-slot:subtitle>{{ __('mxconnect.guarantee.sub') }}</x-slot:subtitle>
    <x-slot:actions>
    @can('manage-parameters')<a href="{{ route('catalog.import', 'garanties') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-ink/10 bg-white px-4 text-sm font-semibold text-[#0B1F3A] hover:bg-paper"><x-tenant.icon name="library" :size="16"/> Formules types</a>@endcan

    @can('manage-parameters')
    <a href="{{ route('guarantees.create') }}" class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">+ {{ __('mxconnect.guarantee.new') }}</a>
    @endcan
    </x-slot:actions>
  </x-tenant.hero>
  @include('tenant.parameters.partials.tabs')

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
            <td class="px-4 py-3">{{ $v ? $currency->format($v->base_contribution_minor) : '—' }} <span class="text-xs text-ink/40">/ {{ $v ? mb_strtolower(__('mxconnect.periodicity.' . $v->periodicity)) : '' }}</span></td>
            <td class="px-4 py-3"><b class="text-leaf-700">{{ $v ? rtrim(rtrim(number_format((float) $v->coverage_rate, 2, ',', ''), '0'), ',') : '—' }} %</b> <span class="text-xs text-ink/40">({{ __('mxconnect.guarantee.copay') }} {{ $v ? rtrim(rtrim(number_format((float) $v->copay_rate, 2, ',', ''), '0'), ',') : '' }} %)</span></td>
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
