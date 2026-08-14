<x-layouts.tenant :title="__('mxconnect.param.act_types')">
  <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.param.act_types') }}</h1>
  <div class="mt-6 grid gap-6 md:grid-cols-3">
    <div class="md:col-span-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.config.code') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.param.label') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.param.category') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($actTypes as $a)
            <tr><td class="px-4 py-2 font-mono text-xs">{{ $a->code }}</td><td class="px-4 py-2">{{ $a->label }}</td><td class="px-4 py-2">{{ $a->category }}</td></tr>
          @empty
            <tr><td colspan="3" class="px-4 py-8 text-center text-ink/40">{{ __('mxconnect.param.empty') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @can('manage-parameters')
    <form method="POST" action="{{ route('act-types.store') }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.param.add') }}</h2>
      <input name="code" placeholder="{{ __('mxconnect.config.code') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      @error('code')<p class="text-xs text-clay">{{ $message }}</p>@enderror
      <input name="label" placeholder="{{ __('mxconnect.param.label') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <input name="category" placeholder="{{ __('mxconnect.param.category') }}" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.param.add') }}</button>
    </form>
    @endcan
  </div>
</x-layouts.tenant>
