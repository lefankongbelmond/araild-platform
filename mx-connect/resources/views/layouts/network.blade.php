<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'MX-CONNECT' }} · MX-CONNECT</title>
    <link rel="icon" href="data:image/svg+xml,{{ rawurlencode('<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 32 32%22><rect width=%2232%22 height=%2232%22 rx=%228%22 fill=%22%230B2559%22/><path d=%22M6 24V9l6 8 6-8v15%22 fill=%22none%22 stroke=%22%2343B75D%22 stroke-width=%222.6%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22/></svg>') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = { theme: { extend: {
        colors: {
          ink:'#0B1F3A', pine:'#123C78', navy:'#0B2559', 'navy-deep':'#071A3D',
          sage:'#2FAE53', green:'#3AA655', mist:'#EAF6EE', clay:'#C0392B', paper:'#F7FAF8'
        },
        fontFamily: { sans:['ui-sans-serif','system-ui','Segoe UI','sans-serif'] }
      }}}
    </script>
</head>
<body class="min-h-screen bg-paper text-ink antialiased">
    <div class="min-h-screen flex flex-col">
        {{-- Top bar --}}
        <header class="border-b border-ink/10 bg-white/90 backdrop-blur sticky top-0 z-10">
            <div class="mx-auto max-w-6xl px-5 h-14 flex items-center justify-between">
                <a href="{{ route('network.mutuals.index') }}" class="flex items-center gap-2">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-navy to-sage text-white text-xs font-bold">MX</span>
                    <span class="font-semibold tracking-tight">MX-<span class="text-sage">CONNECT</span></span>
                    <span class="text-xs text-ink/50 border border-ink/15 rounded px-1.5 py-0.5">{{ __('mxconnect.network') }}</span>
                </a>
                @auth('network')
                <nav class="hidden sm:flex items-center gap-4 text-sm">
                    <a href="{{ route('network.mutuals.index') }}" class="text-ink/70 hover:text-pine">{{ __('mxconnect.mutual.plural') }}</a>
                    <a href="{{ route('network.config.index') }}" class="text-ink/70 hover:text-pine">{{ __('mxconnect.config.title') }}</a>
                    <a href="{{ route('network.dr.index') }}" class="text-ink/70 hover:text-pine">{{ __('mxconnect.dr.nav') }}</a>
                    <a href="{{ route('network.health.index') }}" class="text-ink/70 hover:text-pine">{{ __('mxconnect.health.nav') }}</a>
                    <a href="{{ route('network.consolidation.index') }}" class="text-ink/70 hover:text-pine">{{ __('mxconnect.consolidation.nav') }}</a>
                    <a href="{{ route('network.billing.index') }}" class="text-ink/70 hover:text-pine">{{ __('mxconnect.billing.nav') }}</a>
                </nav>
                @endauth
                @auth('network')
                <form method="POST" action="{{ route('network.logout') }}" class="flex items-center gap-3">
                    @csrf
                    <span class="text-sm text-ink/60">{{ auth('network')->user()->name }}</span>
                    <button class="text-sm text-clay hover:underline">{{ __('mxconnect.auth.logout') }}</button>
                </form>
                @endauth
            </div>
        </header>

        {{-- Flash messages --}}
        <div class="mx-auto w-full max-w-6xl px-5">
            @foreach (['success' => 'pine', 'warning' => 'clay', 'info' => 'sage', 'error' => 'clay'] as $key => $c)
                @if (session($key))
                    <div class="mt-4 rounded-md border border-{{ $c }}/30 bg-{{ $c }}/5 px-4 py-3 text-sm text-{{ $c }}">
                        {{ session($key) }}
                    </div>
                @endif
            @endforeach
        </div>

        <main class="mx-auto w-full max-w-6xl px-5 py-8 flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-ink/10 py-5 text-center text-xs text-ink/40">
            MX-CONNECT — {{ __('mxconnect.footer') }}
        </footer>
    </div>
</body>
</html>
