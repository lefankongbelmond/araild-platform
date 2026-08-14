<x-layouts.tenant :title="__('mxconnect.param.antennas')">
  <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.param.antennas') }}</h1>
  <div class="mt-6 grid gap-6 md:grid-cols-3">
    <div class="md:col-span-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.param.name') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.param.locality') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.param.manager') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($antennas as $a)
            <tr><td class="px-4 py-2 font-medium">{{ $a->name }}</td><td class="px-4 py-2">{{ $a->locality }}</td><td class="px-4 py-2">{{ $a->manager }}</td></tr>
          @empty
            <tr><td colspan="3" class="px-4 py-8 text-center text-ink/40">{{ __('mxconnect.param.empty') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @can('manage-parameters')
    <form method="POST" action="{{ route('antennas.store') }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.param.add') }}</h2>
      <input name="name" placeholder="{{ __('mxconnect.param.name') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      @error('name')<p class="text-xs text-clay">{{ $message }}</p>@enderror
      <input name="locality" placeholder="{{ __('mxconnect.param.locality') }}" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <input name="manager" placeholder="{{ __('mxconnect.param.manager') }}" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.param.add') }}</button>
    </form>
    @endcan
  </div>
</x-layouts.tenant>
