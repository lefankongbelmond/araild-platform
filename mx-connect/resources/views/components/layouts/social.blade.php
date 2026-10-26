@props(['title' => null, 'aside' => null, 'full' => false])
@php
    $viewer = auth('member')->user();
    $unread = $viewer ? $viewer->unreadNotifications()->count() : 0;
    $nav = [
        ['home', 'home', 'mxconnect.social.nav.home'],
        ['feed', 'feed', 'mxconnect.social.nav.feed'],
        ['discover', 'compass', 'mxconnect.social.nav.discover'],
    ];
    $sections = [
        ['organisations', 'building', 'mxconnect.social.nav.organizations'],
        ['projets', 'folder', 'mxconnect.social.nav.projects'],
        ['evenements', 'calendar', 'mxconnect.social.nav.events'],
        ['opportunites', 'rocket', 'mxconnect.social.nav.opportunities'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}MX-CONNECT</title>
    <meta name="description" content="{{ __('mxconnect.social.home.sub') }}">
    <link rel="icon" href="data:image/svg+xml,{{ rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect width="48" height="48" rx="12" fill="#0B2559"/><path d="M13 34V15l7 10" fill="none" stroke="#2E9E4F" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M20 34l13-19M24 22l9 12" fill="none" stroke="#fff" stroke-width="4.5" stroke-linecap="round"/></svg>') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = { theme: { extend: {
        colors: {
          navy: { DEFAULT: '#0B2559', 700: '#123C78', 900: '#071A3D' },
          brand: { DEFAULT: '#2E9E4F', dark: '#1F7A3A', light: '#EAF6EE' },
          ink: '#0B1F3A', pine: '#123C78', sage: '#2E9E4F', mist: '#EAF6EE', clay: '#C0392B', paper: '#F5F7FA',
          sun: { DEFAULT: '#F2994A', light: '#FEF3E8' }, sky: { DEFAULT: '#2F6FDB', light: '#EAF1FD' },
        },
        fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'Roboto', 'sans-serif'] },
        boxShadow: { card: '0 1px 2px rgba(11,31,58,.04), 0 8px 24px -12px rgba(11,31,58,.12)' },
      }}}
    </script>
    <style>
        [hidden] { display: none !important; }
        details > summary { list-style: none; } details > summary::-webkit-details-marker { display: none; }
        .card { background: #fff; border: 1px solid rgba(11,31,58,.07); border-radius: 1rem; box-shadow: 0 1px 2px rgba(11,31,58,.04), 0 8px 24px -12px rgba(11,31,58,.12); }
        .field { width: 100%; border-radius: .75rem; border: 1px solid rgba(11,31,58,.15); background: #fff; padding: .6rem .85rem; font-size: .875rem; }
        .field:focus { outline: none; border-color: #2E9E4F; box-shadow: 0 0 0 3px rgba(46,158,79,.18); }
        .btn-primary { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; border-radius: .75rem; background: #2E9E4F; padding: .6rem 1.1rem; font-size: .875rem; font-weight: 600; color: #fff; transition: background .15s; }
        .btn-primary:hover { background: #1F7A3A; }
        .btn-secondary { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; border-radius: .75rem; border: 1px solid rgba(11,31,58,.15); background: #fff; padding: .6rem 1.1rem; font-size: .875rem; font-weight: 600; color: #0B2559; transition: border-color .15s; }
        .btn-secondary:hover { border-color: #0B2559; }
        :focus-visible { outline: 2px solid #2E9E4F; outline-offset: 2px; }
    </style>
</head>
<body class="min-h-screen bg-paper font-sans text-ink antialiased">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-lg focus:bg-white focus:px-3 focus:py-2">{{ __('mxconnect.social.common.skip') }}</a>

    {{-- ============ TOP BAR ============ --}}
    <header class="sticky top-0 z-30 border-b border-ink/10 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="shrink-0" aria-label="MX-CONNECT">
                <x-social.logo :tagline="false" class="sm:hidden" />
                <x-social.logo class="hidden sm:inline-flex" />
            </a>

            <form action="{{ route('discover') }}" method="GET" role="search" class="hidden flex-1 md:block">
                <label class="relative block max-w-xl">
                    <span class="sr-only">{{ __('mxconnect.social.common.search') }}</span>
                    <x-social.icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink/40" />
                    <input type="search" name="q" value="{{ request()->routeIs('discover') ? request('q') : '' }}"
                           placeholder="{{ __('mxconnect.social.nav.search_placeholder') }}"
                           class="w-full rounded-xl border border-transparent bg-paper py-2.5 pl-10 pr-4 text-sm placeholder:text-ink/40 focus:border-brand focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
                </label>
            </form>

            <nav class="ml-auto flex items-center gap-1 sm:gap-2">
                <a href="{{ route('feed') }}" class="hidden flex-col items-center rounded-lg px-3 py-1 text-[11px] font-medium sm:flex {{ request()->routeIs('feed') ? 'text-brand' : 'text-ink/60 hover:text-navy' }}">
                    <x-social.icon name="home" class="h-5 w-5" />{{ __('mxconnect.social.nav.home') }}
                </a>
                @if ($viewer)
                    <a href="{{ route('my.notifications') }}" class="relative flex flex-col items-center rounded-lg px-3 py-1 text-[11px] font-medium {{ request()->routeIs('my.notifications') ? 'text-brand' : 'text-ink/60 hover:text-navy' }}">
                        <x-social.icon name="bell" class="h-5 w-5" />
                        <span class="hidden sm:block">{{ __('mxconnect.social.nav.notifications') }}</span>
                        @if ($unread > 0)
                            <span class="absolute -top-0.5 right-1.5 min-w-[1.1rem] rounded-full bg-clay px-1 text-center text-[10px] font-bold leading-[1.1rem] text-white">{{ $unread > 99 ? '99+' : $unread }}</span>
                        @endif
                    </a>
                    <details class="relative">
                        <summary class="flex cursor-pointer items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-paper">
                            <x-social.avatar :account="$viewer" size="h-9 w-9" />
                            <span class="hidden text-sm font-semibold text-navy lg:block">{{ $viewer->first_name }}</span>
                        </summary>
                        <div class="card absolute right-0 mt-2 w-60 p-2 text-sm">
                            <a href="{{ route('my.requests') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-paper"><x-social.icon name="clipboard" class="h-4 w-4 text-ink/50" />{{ __('mxconnect.social.nav.my_requests') }}</a>
                            <a href="{{ route('my.organizations') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-paper"><x-social.icon name="building" class="h-4 w-4 text-ink/50" />{{ __('mxconnect.social.nav.my_organizations') }}</a>
                            <a href="{{ route('organizations.create') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-paper"><x-social.icon name="plus" class="h-4 w-4 text-ink/50" />{{ __('mxconnect.social.nav.create_org') }}</a>
                            <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-ink/10 pt-1">
                                @csrf
                                <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-clay hover:bg-paper"><x-social.icon name="logout" class="h-4 w-4" />{{ __('mxconnect.social.nav.logout') }}</button>
                            </form>
                        </div>
                    </details>
                @else
                    <a href="{{ route('login') }}" class="rounded-xl px-3 py-2 text-sm font-semibold text-navy hover:bg-paper">{{ __('mxconnect.social.nav.login') }}</a>
                    <a href="{{ route('register') }}" class="btn-primary hidden sm:inline-flex">{{ __('mxconnect.social.nav.register') }}</a>
                @endif
            </nav>
        </div>
    </header>

    <div class="mx-auto max-w-7xl px-4 pb-24 pt-6 sm:px-6 lg:pb-10">
        <div class="grid gap-6 {{ $full ? '' : 'lg:grid-cols-[220px_minmax(0,1fr)]' }} {{ $aside && ! $full ? 'xl:grid-cols-[220px_minmax(0,1fr)_320px]' : '' }}">

            {{-- ============ LEFT NAV (desktop) ============ --}}
            @unless ($full)
            <aside class="hidden lg:block">
                <nav class="sticky top-24 space-y-6 text-sm">
                    <div class="space-y-1">
                        @foreach ($nav as [$route, $icon, $label])
                            <a href="{{ route($route) }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs($route) ? 'bg-brand-light text-brand-dark' : 'text-ink/70 hover:bg-white hover:text-navy' }}">
                                <x-social.icon :name="$icon" class="h-5 w-5" /> {{ __($label) }}
                            </a>
                        @endforeach
                        @foreach ($sections as [$section, $icon, $label])
                            <a href="{{ route('discover', ['section' => $section]) }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium text-ink/70 transition hover:bg-white hover:text-navy {{ request()->routeIs('discover') && request('section') === $section ? 'bg-white text-navy' : '' }}">
                                <x-social.icon :name="$icon" class="h-5 w-5" /> {{ __($label) }}
                            </a>
                        @endforeach
                    </div>

                    <div>
                        <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-ink/40">{{ __('mxconnect.social.nav.domains') }}</p>
                        <div class="mt-2 space-y-1">
                            @foreach (config('mxconnect.social.domains') as $domain)
                                <a href="{{ route('discover', ['domaine' => $domain]) }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-ink/70 transition hover:bg-white hover:text-navy {{ request('domaine') === $domain ? 'bg-white text-navy' : '' }}">
                                    <x-social.icon :name="$domain" class="h-[18px] w-[18px] text-brand" /> {{ __('mxconnect.social.domains.'.$domain) }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    @if ($viewer)
                        <div>
                            <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-ink/40">{{ __('mxconnect.social.nav.my_spaces') }}</p>
                            <div class="mt-2 space-y-1">
                                <a href="{{ route('my.requests') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 transition {{ request()->routeIs('my.requests') ? 'bg-brand-light text-brand-dark' : 'text-ink/70 hover:bg-white hover:text-navy' }}"><x-social.icon name="clipboard" class="h-[18px] w-[18px]" /> {{ __('mxconnect.social.nav.my_requests') }}</a>
                                <a href="{{ route('my.organizations') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 transition {{ request()->routeIs('my.organizations*') ? 'bg-brand-light text-brand-dark' : 'text-ink/70 hover:bg-white hover:text-navy' }}"><x-social.icon name="building" class="h-[18px] w-[18px]" /> {{ __('mxconnect.social.nav.my_organizations') }}</a>
                            </div>
                        </div>
                    @else
                        <div class="card p-4">
                            <p class="text-sm text-ink/70">{{ __('mxconnect.social.feed.guest_cta') }}</p>
                            <a href="{{ route('register') }}" class="btn-primary mt-3 w-full">{{ __('mxconnect.social.home.cta_join') }}</a>
                        </div>
                    @endif
                </nav>
            </aside>
            @endunless

            {{-- ============ MAIN ============ --}}
            <main id="contenu" class="min-w-0">
                @foreach (['success' => 'border-brand/30 bg-brand-light text-brand-dark', 'error' => 'border-clay/30 bg-clay/5 text-clay'] as $key => $classes)
                    @if (session($key))
                        <div class="mb-5 rounded-xl border px-4 py-3 text-sm {{ $classes }}" role="status">{{ session($key) }}</div>
                    @endif
                @endforeach
                {{-- Business-rule errors raised by the services (not tied to a visible field). --}}
                @php($ruleError = collect(['organization', 'status', 'post_id', 'project_id', 'as', 'parent_id'])->map(fn ($k) => $errors->first($k))->filter()->first())
                @if ($ruleError)
                    <div class="mb-5 rounded-xl border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay" role="alert">{{ $ruleError }}</div>
                @endif

                {{ $slot }}
            </main>

            @if ($aside && ! $full)
                <aside class="hidden space-y-6 xl:block">{{ $aside }}</aside>
            @endif
        </div>
    </div>

    <footer class="hidden border-t border-ink/10 bg-white lg:block">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-6 text-xs text-ink/50">
            <span>&copy; {{ date('Y') }} MX-CONNECT — {{ __('mxconnect.footer') }}</span>
            <span class="flex flex-wrap gap-4">
                <a href="{{ route('directory') }}" class="hover:text-navy">{{ __('mxconnect.social.nav.directory') }}</a>
                <a href="{{ route('comparator') }}" class="hover:text-navy">{{ __('mxconnect.social.nav.comparator') }}</a>
                <a href="{{ route('network.login') }}" class="hover:text-navy">{{ __('mxconnect.social.nav.network') }}</a>
            </span>
        </div>
    </footer>

    {{-- ============ MOBILE BOTTOM NAV ============ --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-ink/10 bg-white/95 backdrop-blur lg:hidden" aria-label="{{ __('mxconnect.social.nav.menu') }}">
        <div class="mx-auto grid max-w-md grid-cols-5 items-end px-2 pb-[max(env(safe-area-inset-bottom),.5rem)] pt-2 text-[11px] font-medium">
            <a href="{{ route('home') }}" class="flex flex-col items-center gap-0.5 {{ request()->routeIs('home') ? 'text-brand' : 'text-ink/60' }}"><x-social.icon name="home" />{{ __('mxconnect.social.nav.home') }}</a>
            <a href="{{ route('discover') }}" class="flex flex-col items-center gap-0.5 {{ request()->routeIs('discover') ? 'text-brand' : 'text-ink/60' }}"><x-social.icon name="compass" />{{ __('mxconnect.social.nav.discover') }}</a>
            <a href="{{ $viewer ? route('feed').'#publier' : route('login') }}" class="-mt-6 flex flex-col items-center gap-1 text-navy">
                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-brand text-white shadow-lg ring-4 ring-white"><x-social.icon name="plus" class="h-7 w-7" /></span>
                {{ __('mxconnect.social.nav.publish') }}
            </a>
            <a href="{{ $viewer ? route('my.notifications') : route('feed') }}" class="relative flex flex-col items-center gap-0.5 {{ request()->routeIs($viewer ? 'my.notifications' : 'feed') ? 'text-brand' : 'text-ink/60' }}">
                <x-social.icon :name="$viewer ? 'bell' : 'feed'" />{{ $viewer ? __('mxconnect.social.nav.notifications') : __('mxconnect.social.nav.feed') }}
                @if ($unread > 0)<span class="absolute right-3 top-0 h-2 w-2 rounded-full bg-clay"></span>@endif
            </a>
            <a href="{{ $viewer ? route('my.requests') : route('login') }}" class="flex flex-col items-center gap-0.5 {{ request()->routeIs('my.*', 'login') ? 'text-brand' : 'text-ink/60' }}"><x-social.icon name="user" />{{ $viewer ? __('mxconnect.social.nav.my_space') : __('mxconnect.social.nav.login') }}</a>
        </div>
    </nav>

    <script>
      // "Partager": native share sheet when available, otherwise copy the link.
      document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-share]');
        if (!btn) return;
        const url = btn.dataset.share;
        try {
          if (navigator.share) { await navigator.share({ url }); return; }
          await navigator.clipboard.writeText(url);
          const label = btn.querySelector('[data-share-label]');
          if (label) { const old = label.textContent; label.textContent = btn.dataset.copied; setTimeout(() => label.textContent = old, 1800); }
        } catch (_) {}
      });
    </script>
</body>
</html>
