<x-layouts.network :title="__('mxconnect.mutual.plural')">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.mutual.plural') }}</h1>
      <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.mutual.index_sub') }}</p>
    </div>
    @can('create', \App\Models\Central\Mutual::class)
      <a href="{{ route('network.mutuals.create') }}"
         class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">
        + {{ __('mxconnect.mutual.create') }}
      </a>
    @endcan
  </div>

  <div class="mt-6 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60">
        <tr>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.mutual.name') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.mutual.country') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.mutual.currency') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.status') }}</th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($mutuals as $mutual)
          @php $badge = ['pending'=>'clay','approved'=>'pine','suspended'=>'ink/40'][$mutual->status] ?? 'ink/40'; @endphp
          <tr>
            <td class="px-4 py-3 font-medium">{{ $mutual->name }}<div class="text-xs text-ink/40">{{ $mutual->slug }}</div></td>
            <td class="px-4 py-3">{{ $mutual->country?->name }}</td>
            <td class="px-4 py-3">{{ $mutual->currency?->code }}</td>
            <td class="px-4 py-3">
              <span class="inline-block rounded-full border border-{{ $badge }}/30 px-2 py-0.5 text-xs text-{{ $badge }}">
                {{ __('mxconnect.mutual.status_'.$mutual->status) }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              @can('approve', $mutual)
                <form method="POST" action="{{ route('network.mutuals.approve', $mutual) }}" class="inline">
                  @csrf<button class="text-pine hover:underline">{{ __('mxconnect.mutual.approve') }}</button>
                </form>
              @endcan
              @can('suspend', $mutual)
                <form method="POST" action="{{ route('network.mutuals.suspend', $mutual) }}" class="inline ml-3">
                  @csrf<button class="text-clay hover:underline">{{ __('mxconnect.mutual.suspend') }}</button>
                </form>
              @endcan
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.mutual.empty') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-4">{{ $mutuals->links() }}</div>
</x-layouts.network>
