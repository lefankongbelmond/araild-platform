<x-layouts.network :title="__('mxconnect.health.title')">
  <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.health.title') }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.health.sub') }}</p>

  @if ($unreachable > 0)
    <div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ __('mxconnect.health.unreachable', ['n' => $unreachable]) }}</div>
  @endif

  <div class="mt-6 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.health.mutual') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.health.db') }}</th>
        <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.health.members') }}</th>
        <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.health.active_subs') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.health.last_activity') }}</th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($rows as $r)
          <tr>
            <td class="px-4 py-2">{{ $r['name'] }}<div class="text-xs text-ink/40 font-mono">{{ $r['id'] }} · {{ $r['status'] }}</div></td>
            <td class="px-4 py-2">
              @if ($r['reachable'])<span class="rounded-full bg-sage/15 px-2 py-0.5 text-xs text-sage">{{ __('mxconnect.health.ok') }}</span>
              @else<span class="rounded-full bg-clay/20 px-2 py-0.5 text-xs text-clay">{{ __('mxconnect.health.down') }}</span>@endif
            </td>
            <td class="px-4 py-2 text-right">{{ $r['reachable'] ? $r['members'] : '—' }}</td>
            <td class="px-4 py-2 text-right">{{ $r['reachable'] ? $r['active_subscriptions'] : '—' }}</td>
            <td class="px-4 py-2 text-ink/60">{{ $r['last_activity'] ? \Illuminate\Support\Carbon::parse($r['last_activity'])->format('d/m/Y H:i') : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.health.empty') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-layouts.network>
