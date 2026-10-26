<x-layouts.network :title="__('mxconnect.config.countries')">
  <a href="{{ route('network.config.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.config.title') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('mxconnect.config.countries') }}</h1>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.config.name') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.mutual.currency') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.mutual.locale') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.config.providers') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @foreach ($countries as $co)
          <tr>
            <td class="px-4 py-2 font-medium">{{ $co->name }} <span class="text-xs text-ink/40">{{ $co->iso2 }}</span></td>
            <td class="px-4 py-2">{{ $co->defaultCurrency?->code }}</td>
            <td class="px-4 py-2">{{ $co->defaultLocale?->name }}</td>
            <td class="px-4 py-2 text-xs text-ink/60">{{ $co->paymentProviders->pluck('name')->join(', ') ?: '—' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @can('manage-config')
    <form method="POST" action="{{ route('network.config.countries.store') }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.config.add') }}</h2>
      <div class="flex gap-2">
        <input name="iso2" placeholder="CM" maxlength="2" required class="w-16 rounded-md border-ink/20 px-3 py-2 text-sm uppercase">
        <input name="name" placeholder="{{ __('mxconnect.config.name') }}" required class="flex-1 rounded-md border-ink/20 px-3 py-2 text-sm">
      </div>
      @error('iso2')<p class="text-xs text-clay">{{ $message }}</p>@enderror
      <select name="default_currency_id" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @foreach ($currencies as $c)<option value="{{ $c->id }}">{{ $c->code }}</option>@endforeach
      </select>
      <select name="default_locale_id" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @foreach ($locales as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
      </select>
      <input name="phone_prefix" placeholder="+237" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <div>
        <div class="text-xs text-ink/60 mb-1">{{ __('mxconnect.config.providers') }}</div>
        @foreach ($providers as $p)
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="providers[]" value="{{ $p->id }}" class="rounded border-ink/30 text-pine">{{ $p->name }}</label>
        @endforeach
      </div>
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.config.add') }}</button>
    </form>
    @endcan
  </div>
</x-layouts.network>
