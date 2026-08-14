<x-layouts.tenant :title="__('mxconnect.subscription.plural')">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.subscription.plural') }}</h1>
    @can('subscription.capture')
      <a href="{{ route('subscriptions.create') }}" class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">+ {{ __('mxconnect.subscription.new') }}</a>
    @endcan
  </div>

  <div class="mt-6 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.member.name') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.guarantee.plural') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.subscription.contribution') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.status') }}</th>
        <th class="px-4 py-2"></th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($subscriptions as $s)
          @php $badge = ['captured'=>'clay','validated'=>'pine','terminated'=>'ink/40'][$s->status->value] ?? 'ink/40'; @endphp
          <tr>
            <td class="px-4 py-3 font-medium">{{ $s->member?->last_name }} {{ $s->member?->first_name }}</td>
            <td class="px-4 py-3">{{ $s->guaranteeVersion?->guarantee?->name }} <span class="text-xs text-ink/40">V{{ $s->guaranteeVersion?->version_no }}</span></td>
            <td class="px-4 py-3">{{ $currency->format($s->contribution_minor) }} <span class="text-xs text-ink/40">/ {{ $s->guaranteeVersion?->periodicity }}</span></td>
            <td class="px-4 py-3"><span class="rounded-full border border-{{ $badge }}/30 px-2 py-0.5 text-xs text-{{ $badge }}">{{ __('mxconnect.subscription.status_'.$s->status->value) }}</span></td>
            <td class="px-4 py-3 text-right"><a href="{{ route('subscriptions.show', $s) }}" class="text-pine hover:underline">{{ __('mxconnect.member.open') }}</a></td>
          </tr>
        @empty
          <tr><td colspan="5" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.subscription.empty') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $subscriptions->links() }}</div>
</x-layouts.tenant>
