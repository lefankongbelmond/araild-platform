<x-layouts.network :title="__('mxconnect.config.currencies')">
  <a href="{{ route('network.config.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.config.title') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('mxconnect.config.currencies') }}</h1>

  <div class="mt-6 grid gap-6 md:grid-cols-3">
    <div class="md:col-span-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.config.code') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.config.name') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.config.symbol') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.config.minor_unit') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.status') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @foreach ($currencies as $c)
          <tr>
            <td class="px-4 py-2 font-medium">{{ $c->code }}</td>
            <td class="px-4 py-2">{{ $c->name }}</td>
            <td class="px-4 py-2">{{ $c->symbol }}</td>
            <td class="px-4 py-2">{{ $c->minor_unit }}</td>
            <td class="px-4 py-2">
              <form method="POST" action="{{ route('network.config.currencies.toggle', $c) }}">@csrf
                <button class="text-xs {{ $c->active ? 'text-pine' : 'text-ink/40' }} hover:underline">
                  {{ $c->active ? __('mxconnect.active') : __('mxconnect.inactive') }}
                </button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    @can('manage-config')
    <form method="POST" action="{{ route('network.config.currencies.store') }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.config.add') }}</h2>
      <input name="code" placeholder="{{ __('mxconnect.config.code') }} (XAF)" maxlength="3" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm uppercase">
      @error('code')<p class="text-xs text-clay">{{ $message }}</p>@enderror
      <input name="name" placeholder="{{ __('mxconnect.config.name') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <input name="symbol" placeholder="{{ __('mxconnect.config.symbol') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <input name="minor_unit" type="number" min="0" max="4" value="0" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm" title="{{ __('mxconnect.config.minor_unit') }}">
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.config.add') }}</button>
    </form>
    @endcan
  </div>
</x-layouts.network>
