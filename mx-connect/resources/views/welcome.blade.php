<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('mxconnect.welcome.meta_description') }}">
    <title>MX-CONNECT — {{ __('mxconnect.welcome.title') }}</title>
    <link rel="icon" href="data:image/svg+xml,{{ rawurlencode('<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 32 32%22><rect width=%2232%22 height=%2232%22 rx=%228%22 fill=%22%230B2559%22/><path d=%22M6 24V9l6 8 6-8v15%22 fill=%22none%22 stroke=%22%2343B75D%22 stroke-width=%222.6%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22/></svg>') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = { theme: { extend: {
        colors: {
          ink:'#0B1F3A', pine:'#123C78', navy:'#0B2559', 'navy-deep':'#071A3D',
          sage:'#2FAE53', green:'#3AA655', mist:'#EAF6EE', clay:'#C0392B', paper:'#F7FAF8'
        },
        fontFamily: { sans:['ui-sans-serif','system-ui','Segoe UI','sans-serif'] },
        boxShadow: { glow: '0 20px 60px -20px rgba(11,37,89,0.35)' }
      }}}
    </script>
    <style>
      .bg-grid { background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,.14) 1px, transparent 0); background-size: 28px 28px; }
      .hero-orbit { background: conic-gradient(from 200deg, #3AA655, #0B2559 55%, #3AA655 100%); }
      .card-hover { transition: transform .25s ease, box-shadow .25s ease; }
      .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -18px rgba(11,37,89,.28); }
    </style>
</head>
<body class="bg-paper text-ink antialiased">

    {{-- ============ HEADER ============ --}}
    <header class="sticky top-0 z-30 border-b border-ink/10 bg-white/85 backdrop-blur">
        <div class="mx-auto max-w-7xl px-5 h-16 flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-navy via-navy to-green text-white font-bold text-sm shadow-glow">MX</span>
                <span class="text-lg font-bold tracking-tight text-navy">MX-<span class="text-green">CONNECT</span></span>
            </a>
            <nav class="hidden md:flex items-center gap-7 text-sm font-medium text-ink/70">
                <a href="#solution" class="hover:text-navy transition">{{ __('mxconnect.welcome.nav_solution') }}</a>
                <a href="#fonctionnement" class="hover:text-navy transition">{{ __('mxconnect.welcome.nav_how') }}</a>
                <a href="{{ route('directory') }}" class="hover:text-navy transition">{{ __('mxconnect.directory.title') }}</a>
                <a href="{{ route('comparator') }}" class="hover:text-navy transition">{{ __('mxconnect.comparator.title') }}</a>
            </nav>
            <div class="flex items-center gap-3">
                <a href="{{ route('network.login') }}" class="hidden sm:inline-block text-sm font-medium text-ink/70 hover:text-navy transition">{{ __('mxconnect.auth.login') }}</a>
                <a href="{{ route('directory') }}" class="inline-flex items-center gap-1.5 rounded-full bg-navy px-4 py-2 text-sm font-semibold text-white shadow-glow hover:bg-navy-deep transition">
                    {{ __('mxconnect.welcome.cta_directory') }}
                </a>
            </div>
        </div>
    </header>

    {{-- ============ HERO ============ --}}
    <section class="relative overflow-hidden bg-navy">
        <div class="absolute inset-0 bg-grid opacity-40"></div>
        <div class="absolute -top-40 -right-40 h-[32rem] w-[32rem] rounded-full hero-orbit opacity-20 blur-3xl"></div>
        <div class="absolute -bottom-52 -left-32 h-[28rem] w-[28rem] rounded-full bg-green/20 blur-3xl"></div>

        <div class="relative mx-auto max-w-7xl px-5 pt-20 pb-24 lg:pt-28 lg:pb-32">
            <div class="grid lg:grid-cols-2 gap-14 items-center">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3.5 py-1.5 text-xs font-medium text-white/80">
                        <span class="h-1.5 w-1.5 rounded-full bg-green animate-pulse"></span>
                        {{ __('mxconnect.welcome.eyebrow') }}
                    </span>
                    <h1 class="mt-6 text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-white leading-[1.08]">
                        {{ __('mxconnect.welcome.hero_title_1') }}
                        <span class="bg-gradient-to-r from-green to-emerald-300 bg-clip-text text-transparent">{{ __('mxconnect.welcome.hero_title_2') }}</span>
                    </h1>
                    <p class="mt-6 text-lg text-white/70 max-w-xl">{{ __('mxconnect.welcome.hero_sub') }}</p>

                    <div class="mt-9 flex flex-wrap items-center gap-4">
                        <a href="{{ route('comparator') }}" class="inline-flex items-center gap-2 rounded-full bg-green px-6 py-3.5 text-sm font-semibold text-navy-deep shadow-glow hover:brightness-110 transition">
                            {{ __('mxconnect.welcome.cta_primary') }}
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none"><path d="M4 10h12M12 5l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                        <a href="{{ route('network.login') }}" class="inline-flex items-center gap-2 rounded-full border border-white/25 px-6 py-3.5 text-sm font-semibold text-white hover:bg-white/10 transition">
                            {{ __('mxconnect.welcome.cta_secondary') }}
                        </a>
                    </div>

                    <div class="mt-12 grid grid-cols-3 gap-6 max-w-md">
                        <div>
                            <div class="text-2xl font-extrabold text-white">100%</div>
                            <div class="mt-1 text-xs text-white/55">{{ __('mxconnect.welcome.stat_isolation') }}</div>
                        </div>
                        <div>
                            <div class="text-2xl font-extrabold text-white">24/7</div>
                            <div class="mt-1 text-xs text-white/55">{{ __('mxconnect.welcome.stat_uptime') }}</div>
                        </div>
                        <div>
                            <div class="text-2xl font-extrabold text-white">FR/EN</div>
                            <div class="mt-1 text-xs text-white/55">{{ __('mxconnect.welcome.stat_locales') }}</div>
                        </div>
                    </div>
                </div>

                {{-- Hero visual: stylised product card --}}
                <div class="relative">
                    <div class="rounded-2xl border border-white/10 bg-white/[0.06] backdrop-blur-xl p-6 shadow-glow">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-clay/70"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-yellow-400/70"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-green/70"></span>
                            </div>
                            <span class="text-[11px] font-medium text-white/50">{{ __('mxconnect.welcome.panel_label') }}</span>
                        </div>

                        <div class="mt-5 space-y-3">
                            @foreach ([
                                ['label' => __('mxconnect.welcome.panel_row_1'), 'value' => '2 481', 'up' => true],
                                ['label' => __('mxconnect.welcome.panel_row_2'), 'value' => '97,4%', 'up' => true],
                                ['label' => __('mxconnect.welcome.panel_row_3'), 'value' => '14', 'up' => false],
                            ] as $row)
                            <div class="flex items-center justify-between rounded-xl bg-white/5 border border-white/10 px-4 py-3.5">
                                <span class="text-sm text-white/75">{{ $row['label'] }}</span>
                                <span class="flex items-center gap-1.5 text-sm font-semibold text-white">
                                    {{ $row['value'] }}
                                    <svg class="h-3.5 w-3.5 {{ $row['up'] ? 'text-green' : 'text-white/40' }}" viewBox="0 0 20 20" fill="none">
                                        <path d="M4 12l5-5 3 3 5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </div>
                            @endforeach
                        </div>

                        <div class="mt-5 rounded-xl bg-gradient-to-r from-green/20 to-transparent border border-green/20 px-4 py-3.5">
                            <div class="text-xs text-white/60">{{ __('mxconnect.welcome.panel_footer_label') }}</div>
                            <div class="mt-1 text-sm font-semibold text-white">{{ __('mxconnect.welcome.panel_footer_value') }}</div>
                        </div>
                    </div>

                    <div class="absolute -bottom-6 -left-6 hidden sm:flex items-center gap-3 rounded-2xl bg-white px-5 py-4 shadow-2xl">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-mist text-green">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none"><path d="M4 10.5l4 4 8-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <div>
                            <div class="text-xs text-ink/50">{{ __('mxconnect.welcome.badge_label') }}</div>
                            <div class="text-sm font-semibold text-navy">{{ __('mxconnect.welcome.badge_value') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ TRUSTED / LOGOS BAND ============ --}}
    <section class="border-b border-ink/10 bg-white">
        <div class="mx-auto max-w-7xl px-5 py-7 flex flex-wrap items-center justify-center gap-x-10 gap-y-3 text-xs font-semibold uppercase tracking-wider text-ink/35">
            <span>{{ __('mxconnect.welcome.band_1') }}</span>
            <span>{{ __('mxconnect.welcome.band_2') }}</span>
            <span>{{ __('mxconnect.welcome.band_3') }}</span>
            <span>{{ __('mxconnect.welcome.band_4') }}</span>
            <span>{{ __('mxconnect.welcome.band_5') }}</span>
        </div>
    </section>

    {{-- ============ FEATURES ============ --}}
    <section id="solution" class="mx-auto max-w-7xl px-5 py-24">
        <div class="max-w-2xl">
            <span class="text-xs font-bold uppercase tracking-widest text-green">{{ __('mxconnect.welcome.features_eyebrow') }}</span>
            <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold tracking-tight text-navy">{{ __('mxconnect.welcome.features_title') }}</h2>
            <p class="mt-4 text-ink/60">{{ __('mxconnect.welcome.features_sub') }}</p>
        </div>

        <div class="mt-14 grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ([
                ['icon' => 'shield', 'title' => __('mxconnect.welcome.feat_1_title'), 'body' => __('mxconnect.welcome.feat_1_body')],
                ['icon' => 'globe',  'title' => __('mxconnect.welcome.feat_2_title'), 'body' => __('mxconnect.welcome.feat_2_body')],
                ['icon' => 'card',   'title' => __('mxconnect.welcome.feat_3_title'), 'body' => __('mxconnect.welcome.feat_3_body')],
                ['icon' => 'chart',  'title' => __('mxconnect.welcome.feat_4_title'), 'body' => __('mxconnect.welcome.feat_4_body')],
                ['icon' => 'users',  'title' => __('mxconnect.welcome.feat_5_title'), 'body' => __('mxconnect.welcome.feat_5_body')],
                ['icon' => 'bolt',   'title' => __('mxconnect.welcome.feat_6_title'), 'body' => __('mxconnect.welcome.feat_6_body')],
            ] as $f)
            <div class="card-hover rounded-2xl border border-ink/10 bg-white p-7">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-mist text-green">
                    @switch($f['icon'])
                        @case('shield')
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6l7-3z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                            @break
                        @case('globe')
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 12h17M12 3.5c2.5 2.4 3.8 5.4 3.8 8.5s-1.3 6.1-3.8 8.5c-2.5-2.4-3.8-5.4-3.8-8.5S9.5 5.9 12 3.5z" stroke="currentColor" stroke-width="1.7"/></svg>
                            @break
                        @case('card')
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><rect x="3" y="6" width="18" height="13" rx="2.2" stroke="currentColor" stroke-width="1.7"/><path d="M3 10.5h18" stroke="currentColor" stroke-width="1.7"/></svg>
                            @break
                        @case('chart')
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M4 20V10M11 20V4M18 20v-7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                            @break
                        @case('users')
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8.5" r="3.2" stroke="currentColor" stroke-width="1.7"/><path d="M2.8 20c.7-3.4 3.1-5.3 6.2-5.3s5.5 1.9 6.2 5.3M16 8.7a3 3 0 110-6M21.2 20c-.4-2.2-1.5-3.7-3.2-4.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                            @break
                        @case('bolt')
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M13 2L4.5 13.5H11L10 22l8.5-11.5H12l1-8.5z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                            @break
                    @endswitch
                </span>
                <h3 class="mt-5 font-semibold text-lg text-navy">{{ $f['title'] }}</h3>
                <p class="mt-2 text-sm text-ink/60 leading-relaxed">{{ $f['body'] }}</p>
            </div>
            @endforeach
        </div>
    </section>

    {{-- ============ HOW IT WORKS ============ --}}
    <section id="fonctionnement" class="bg-mist/60">
        <div class="mx-auto max-w-7xl px-5 py-24">
            <div class="max-w-2xl">
                <span class="text-xs font-bold uppercase tracking-widest text-navy">{{ __('mxconnect.welcome.how_eyebrow') }}</span>
                <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold tracking-tight text-navy">{{ __('mxconnect.welcome.how_title') }}</h2>
            </div>

            <div class="mt-14 grid md:grid-cols-3 gap-8">
                @foreach ([
                    ['n' => '01', 'title' => __('mxconnect.welcome.step_1_title'), 'body' => __('mxconnect.welcome.step_1_body')],
                    ['n' => '02', 'title' => __('mxconnect.welcome.step_2_title'), 'body' => __('mxconnect.welcome.step_2_body')],
                    ['n' => '03', 'title' => __('mxconnect.welcome.step_3_title'), 'body' => __('mxconnect.welcome.step_3_body')],
                ] as $i => $s)
                <div class="relative">
                    <div class="text-5xl font-black text-navy/10">{{ $s['n'] }}</div>
                    <h3 class="mt-2 text-lg font-semibold text-navy">{{ $s['title'] }}</h3>
                    <p class="mt-2 text-sm text-ink/60 leading-relaxed">{{ $s['body'] }}</p>
                    @if ($i < 2)
                        <div class="hidden md:block absolute top-6 -right-4 w-8 h-px bg-navy/15"></div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ CTA BAND ============ --}}
    <section class="mx-auto max-w-7xl px-5 py-24">
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-navy to-navy-deep px-8 py-16 sm:px-16 text-center shadow-glow">
            <div class="absolute -top-24 -right-24 h-72 w-72 rounded-full bg-green/25 blur-3xl"></div>
            <div class="absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-green/10 blur-3xl"></div>
            <div class="relative">
                <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white max-w-2xl mx-auto">{{ __('mxconnect.welcome.cta_band_title') }}</h2>
                <p class="mt-4 text-white/70 max-w-xl mx-auto">{{ __('mxconnect.welcome.cta_band_sub') }}</p>
                <div class="mt-9 flex flex-wrap items-center justify-center gap-4">
                    <a href="{{ route('comparator') }}" class="inline-flex items-center gap-2 rounded-full bg-green px-7 py-3.5 text-sm font-semibold text-navy-deep shadow-glow hover:brightness-110 transition">
                        {{ __('mxconnect.welcome.cta_primary') }}
                    </a>
                    <a href="{{ route('directory') }}" class="inline-flex items-center gap-2 rounded-full border border-white/25 px-7 py-3.5 text-sm font-semibold text-white hover:bg-white/10 transition">
                        {{ __('mxconnect.directory.title') }}
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ FOOTER ============ --}}
    <footer class="border-t border-ink/10 bg-white">
        <div class="mx-auto max-w-7xl px-5 py-14 grid sm:grid-cols-2 lg:grid-cols-4 gap-10">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-navy to-green text-white text-xs font-bold">MX</span>
                    <span class="font-bold text-navy">MX-<span class="text-green">CONNECT</span></span>
                </div>
                <p class="mt-4 text-sm text-ink/50 leading-relaxed">{{ __('mxconnect.welcome.footer_tagline') }}</p>
                <p class="mt-3 text-[11px] font-semibold uppercase tracking-widest text-ink/30">{{ __('mxconnect.welcome.footer_baseline') }}</p>
            </div>
            <div>
                <div class="text-xs font-bold uppercase tracking-widest text-ink/40">{{ __('mxconnect.welcome.footer_col_platform') }}</div>
                <ul class="mt-4 space-y-2.5 text-sm text-ink/60">
                    <li><a href="{{ route('directory') }}" class="hover:text-navy">{{ __('mxconnect.directory.title') }}</a></li>
                    <li><a href="{{ route('comparator') }}" class="hover:text-navy">{{ __('mxconnect.comparator.title') }}</a></li>
                    <li><a href="{{ route('health') }}" class="hover:text-navy">{{ __('mxconnect.health.nav') }}</a></li>
                </ul>
            </div>
            <div>
                <div class="text-xs font-bold uppercase tracking-widest text-ink/40">{{ __('mxconnect.welcome.footer_col_access') }}</div>
                <ul class="mt-4 space-y-2.5 text-sm text-ink/60">
                    <li><a href="{{ route('network.login') }}" class="hover:text-navy">{{ __('mxconnect.auth.login') }}</a></li>
                </ul>
            </div>
            <div>
                <div class="text-xs font-bold uppercase tracking-widest text-ink/40">{{ __('mxconnect.welcome.footer_col_legal') }}</div>
                <p class="mt-4 text-sm text-ink/50">www.mx-connect.com</p>
            </div>
        </div>
        <div class="border-t border-ink/10">
            <div class="mx-auto max-w-7xl px-5 py-5 text-xs text-ink/40 flex flex-col sm:flex-row items-center justify-between gap-2">
                <span>&copy; {{ date('Y') }} MX-CONNECT — {{ __('mxconnect.footer') }}</span>
                <span class="text-ink/30">{{ __('mxconnect.welcome.footer_baseline') }}</span>
            </div>
        </div>
    </footer>
</body>
</html>
