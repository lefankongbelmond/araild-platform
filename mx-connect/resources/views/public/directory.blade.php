<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ __('mxconnect.directory.title') }} — MX-CONNECT</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{ink:'#0B1F3A',pine:'#123C78',navy:'#0B2559',sage:'#2FAE53',green:'#3AA655',mist:'#EAF6EE',paper:'#F7FAF8'}}}}</script>
</head>
<body class="bg-paper text-ink">
  <header class="border-b border-ink/10 bg-white">
    <div class="mx-auto max-w-5xl px-5 h-16 flex items-center gap-2">
      <a href="/" class="flex items-center gap-2"><span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-navy to-sage text-white text-xs font-bold">MX</span><span class="font-semibold text-lg">MX-<span class="text-sage">CONNECT</span></span></a>
      <span class="ml-2 text-ink/50">{{ __('mxconnect.directory.title') }}</span>
      <a href="{{ route('comparator') }}" class="ml-auto text-sm text-pine hover:underline">{{ __('mxconnect.comparator.title') }} →</a>
    </div>
  </header>

  <main class="mx-auto max-w-5xl px-5 py-10">
    <h1 class="text-3xl font-semibold tracking-tight">{{ __('mxconnect.directory.heading') }}</h1>
    <p class="mt-2 text-ink/60">{{ __('mxconnect.directory.sub') }}</p>

    <form method="GET" class="mt-6">
      <select name="country" onchange="this.form.submit()" class="rounded-md border-ink/20 bg-white px-3 py-2 text-sm">
        <option value="">{{ __('mxconnect.directory.all_countries') }}</option>
        @foreach ($countries as $c)<option value="{{ $c->id }}" @selected($countryId==$c->id)>{{ $c->name }}</option>@endforeach
      </select>
    </form>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      @forelse ($mutuals as $m)
        <div class="rounded-xl border border-ink/10 bg-white p-5">
          <div class="text-lg font-semibold">{{ $m->name }}</div>
          <div class="mt-1 text-sm text-ink/50">{{ $m->country?->name }}</div>
          @if ($m->city || $m->region)<div class="mt-3 text-sm text-pine">{{ collect([$m->city, $m->region])->filter()->join(', ') }}</div>@endif
        </div>
      @empty
        <p class="text-ink/40">{{ __('mxconnect.directory.empty') }}</p>
      @endforelse
    </div>
    <div class="mt-6">{{ $mutuals->links() }}</div>
  </main>

  <footer class="border-t border-ink/10 py-6 text-center text-xs text-ink/40">MX-CONNECT</footer>
</body>
</html>
