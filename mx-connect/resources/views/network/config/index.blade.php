<x-layouts.network :title="__('mxconnect.config.title')">
  <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.config.title') }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.config.sub') }}</p>

  <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
    @foreach ([
      'currencies' => ['network.config.currencies', 'mxconnect.config.currencies'],
      'locales'    => ['network.config.locales', 'mxconnect.config.locales'],
      'countries'  => ['network.config.countries', 'mxconnect.config.countries'],
      'providers'  => ['network.config.providers', 'mxconnect.config.providers'],
    ] as $key => [$route, $label])
      <a href="{{ route($route) }}" class="rounded-lg border border-ink/10 bg-white p-4 hover:border-pine transition">
        <div class="text-3xl font-semibold text-pine">{{ $counts[$key] }}</div>
        <div class="mt-1 text-sm text-ink/70">{{ __($label) }}</div>
      </a>
    @endforeach
  </div>

  <p class="mt-6 rounded-md bg-mist px-4 py-3 text-xs text-ink/60">{{ __('mxconnect.config.data_note') }}</p>
</x-layouts.network>
