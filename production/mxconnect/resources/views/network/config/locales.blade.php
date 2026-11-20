@php use App\Support\Locales; @endphp
<x-layouts.network :title="__('mxconnect.config.locales')">
  <a href="{{ route('network.config.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.config.title') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('mxconnect.config.locales') }}</h1>
  <p class="mt-1 max-w-3xl text-sm text-ink/60">{{ __('Une langue activée est proposée à tous dans le sélecteur de langue. Les textes non encore traduits s’affichent en français. Les traductions fournies sont à faire relire par un locuteur natif.') }}</p>
  @if (session('error'))<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ session('error') }}</div>@endif

  <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
    <div class="md:col-span-2 space-y-3">
      @foreach ($locales as $l)
        @php
          $known = Locales::isSupported($l->code);
          $pct = $known ? Locales::completeness($l->code) : null;
        @endphp
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-ink/10 bg-white p-4 text-sm">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2"><b lang="{{ $l->code }}" dir="{{ Locales::direction($l->code) }}">{{ $known ? Locales::name($l->code) : ($l->native_name ?: $l->name) }}</b>
              <span class="font-mono text-xs text-ink/50">{{ $l->code }}</span>
              @if (Locales::isRtl($l->code))<span class="rounded-full bg-mist px-2 py-0.5 text-xs text-ink/60">{{ __('droite à gauche') }}</span>@endif</div>
            <div class="mt-1 text-xs text-ink/55">
              @if ($l->code === Locales::SOURCE){{ __('Langue de référence de la plateforme.') }}
              @elseif (! $known){{ __('Aucune traduction livrée pour cette langue : tout s’afficherait en français.') }}
              @else{{ __('Textes déjà traduisibles traduits : :pct %', ['pct' => $pct]) }}@endif
            </div>
            @if ($known && $l->code !== Locales::SOURCE)
              <div class="mt-2 overflow-hidden rounded-full bg-mist" style="height:6px;width:12rem;max-width:100%"><div class="bg-pine" style="height:100%;width:{{ (int) $pct }}%"></div></div>
            @endif
          </div>
          <form method="POST" action="{{ route('network.config.locales.toggle', $l) }}">@csrf
            <button class="rounded-md border px-3 py-1.5 text-xs font-medium {{ $l->active ? 'border-pine text-pine' : 'border-ink/15 text-ink/50' }}" @disabled($l->code === Locales::SOURCE)>{{ $l->active ? __('mxconnect.active') : __('mxconnect.inactive') }}</button>
          </form>
        </div>
      @endforeach
    </div>
    @can('manage-config')
    <form method="POST" action="{{ route('network.config.locales.store') }}" class="self-start rounded-lg border border-ink/10 bg-white p-4 space-y-3">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.config.add') }}</h2>
      <p class="text-xs text-ink/55">{{ __('Une nouvelle langue n’est utile qu’avec ses fichiers de traduction (lang/<code>.json).') }}</p>
      <input name="code" placeholder="fr / en / pt" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      @error('code')<p class="text-xs text-clay">{{ $message }}</p>@enderror
      <input name="name" placeholder="{{ __('mxconnect.config.name') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.config.add') }}</button>
    </form>
    @endcan
  </div>
</x-layouts.network>
