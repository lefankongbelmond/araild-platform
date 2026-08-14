<x-layouts.network :title="__('mxconnect.config.providers')">
  <a href="{{ route('network.config.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.config.title') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('mxconnect.config.providers') }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.config.providers_sub') }}</p>

  <div class="mt-6 grid gap-6 md:grid-cols-3">
    <div class="md:col-span-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.config.code') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.config.name') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.status') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @foreach ($providers as $p)
          <tr>
            <td class="px-4 py-2 font-mono text-xs">{{ $p->code }}</td>
            <td class="px-4 py-2">{{ $p->name }}</td>
            <td class="px-4 py-2">
              <form method="POST" action="{{ route('network.config.providers.toggle', $p) }}">@csrf
                <button class="text-xs {{ $p->active ? 'text-pine' : 'text-ink/40' }} hover:underline">{{ $p->active ? __('mxconnect.active') : __('mxconnect.inactive') }}</button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @can('manage-config')
    <form method="POST" action="{{ route('network.config.providers.store') }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.config.add') }}</h2>
      <input name="code" placeholder="mtn_momo" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm font-mono">
      @error('code')<p class="text-xs text-clay">{{ $message }}</p>@enderror
      <input name="name" placeholder="{{ __('mxconnect.config.name') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <input name="driver" placeholder="App\Payments\Drivers\..." class="w-full rounded-md border-ink/20 px-3 py-2 text-sm font-mono text-xs">
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.config.add') }}</button>
    </form>
    @endcan
  </div>
</x-layouts.network>
