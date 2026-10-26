<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'MX-CONNECT' }} · MX-CONNECT</title>
    <link rel="icon" href="/brand/favicon.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = { theme: { extend: {
        colors: {
          ink:'#0B1F3A', pine:'#0A5FB5', navy:'#021F5A', 'navy-deep':'#011544',
          sage:'#4F992F', green:'#4F992F', mist:'#EAF6EE', clay:'#C0392B', paper:'#F7FAF8'
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
                    <img src="/brand/mx-mark-128.webp" alt="" class="h-8 w-8">
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
                    @if (auth('network')->user()?->hasRole('super_admin'))
                    <a href="{{ route('network.demos.index') }}" class="text-ink/70 hover:text-pine">Démos</a>
                    @endif
                    @if (auth('network')->user()?->hasAnyRole(['super_admin', 'moderator']))
                    <a href="{{ route('network.moderation.index') }}" class="text-ink/70 hover:text-pine">Modération</a>
                    <a href="{{ route('network.social.sponsorships') }}" class="text-ink/70 hover:text-pine">Sponsorisations</a>
                    <a href="{{ route('network.social.projects') }}" class="text-ink/70 hover:text-pine">Projets et offres</a>
                    <a href="{{ route('network.social.settings') }}" class="text-ink/70 hover:text-pine">Réglages réseau</a>
                    @endif
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
