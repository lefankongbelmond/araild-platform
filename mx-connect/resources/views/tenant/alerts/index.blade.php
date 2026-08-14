<x-layouts.tenant :title="__('mxconnect.alert.plural')">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.alert.plural') }}</h1>
      <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.alert.sub') }}</p>
    </div>
    <div class="flex gap-2 text-sm">
      @if ($counts['critical'])<span class="rounded-full bg-clay/10 px-3 py-1 text-clay">{{ $counts['critical'] }} {{ __('mxconnect.alert.level_critical') }}</span>@endif
      @if ($counts['watch'])<span class="rounded-full bg-sage/10 px-3 py-1 text-sage">{{ $counts['watch'] }} {{ __('mxconnect.alert.level_watch') }}</span>@endif
    </div>
  </div>

  <div class="mt-6 flex gap-2 text-sm">
    <a href="{{ route('alerts.index') }}" class="rounded-md px-3 py-1.5 {{ ! $level ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ __('mxconnect.alert.all') }}</a>
    @foreach (['critical','watch','normal'] as $lv)
      <a href="{{ route('alerts.index', ['level' => $lv]) }}" class="rounded-md px-3 py-1.5 {{ $level === $lv ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ __('mxconnect.alert.level_'.$lv) }}</a>
    @endforeach
  </div>

  <div class="mt-4 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.alert.level') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.alert.category') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.alert.detail') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.alert.date') }}</th>
        <th class="px-4 py-2"></th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($alerts as $a)
          @php $lb = ['critical'=>'clay','watch'=>'sage','normal'=>'ink/40'][$a->level] ?? 'ink/40'; @endphp
          <tr>
            <td class="px-4 py-3"><span class="rounded-full border border-{{ $lb }}/30 px-2 py-0.5 text-xs text-{{ $lb }}">{{ __('mxconnect.alert.level_'.$a->level) }}</span></td>
            <td class="px-4 py-3">{{ __('mxconnect.alert.cat_'.$a->category) }}</td>
            <td class="px-4 py-3 text-ink/60">{{ __('mxconnect.alert.code_'.($a->details['code'] ?? 'unknown')) }}</td>
            <td class="px-4 py-3 text-ink/50">{{ $a->created_at?->format('d/m/Y H:i') }}</td>
            <td class="px-4 py-3 text-right">
              @if (auth()->user()?->hasAnyRole(['controller_validator','mutual_admin','security_admin']))
                <form method="POST" action="{{ route('alerts.clear', $a) }}">@csrf<button class="text-xs text-pine hover:underline">{{ __('mxconnect.alert.clear') }}</button></form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.alert.empty') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $alerts->links() }}</div>
</x-layouts.tenant>
