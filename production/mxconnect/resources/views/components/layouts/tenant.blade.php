@php
  // Alertes du réseau social (éléments à traiter), calculées une fois par page.
  $navAlerts = [];
  if (auth()->check() && function_exists('tenancy') && tenancy()->initialized) {
      try {
          $mid = tenant('id');
          $navAlerts = [
              'online-adhesions.index' => \App\Models\Social\AdhesionRequest::where('mutual_id', $mid)->where('status', 'submitted')->count(),
              'conversations.index'    => (int) \App\Models\Social\Conversation::where('mutual_id', $mid)->sum('mutual_unread'),
              'engagement.index'       => \App\Models\Social\SocialRequest::where('mutual_id', $mid)->where('status', 'submitted')
                  ->when(! auth()->user()->hasRole('mutual_admin'), fn ($q) => $q->where('kind', '!=', 'application'))->count(),
              'social-presence.index'  => \App\Models\Social\ContactRequest::where('mutual_id', $mid)->where('status', 'new')->count()
                  + \App\Models\Social\GroupMember::where('status', 'pending')->whereIn('group_id', \App\Models\Social\Group::where('mutual_id', $mid)->select('id'))->count(),
          ];
      } catch (\Throwable $e) {
          $navAlerts = [];   // tables pas encore migrées : aucune alerte plutôt qu'une erreur
      }
  }
  // Menu (App\Support\TenantNav) filtré par la matrice des droits (App\Support\TenantAccess), aussi appliquée à chaque route.
  $navGroups = \App\Support\TenantNav::groups();
  $icons = [
      'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
      'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
      'coins' => '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18M7 6h1v4M16.71 13.88l.7.71-2.82 2.82"/>',
      'clipboard' => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2M9 14l2 2 4-4"/>',
      'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
      'wallet' => '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
      'book' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>',
      'chart' => '<path d="M3 3v18h18M7 16l4-4 3 3 5-6"/>',
      'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l4 2"/>',
      'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/>',
      'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3M12 9v4M12 17h.01"/>',
      'zap' => '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
      'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11"/>',
      'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 0 20M12 2a15.3 15.3 0 0 0 0 20"/>',
      'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
      'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
      'heart' => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7z"/>',
      'pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
      'stethoscope' => '<path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6 6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/><path d="M8 15v1a6 6 0 0 0 6 6 6 6 0 0 0 6-6v-4"/><circle cx="20" cy="10" r="2"/>',
      'pill' => '<path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7"/><path d="m8.5 8.5 7 7"/>',
      'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
      'send' => '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
      'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><path d="M8 7h8M8 11h8M8 15h5"/>',
      'calculator' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/>',
      'pie' => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
      'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
      'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
      'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
      'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  ];
  $svg = fn ($n, $s = 20) => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($icons[$n] ?? '').'</svg>';
  // Élément actif : route exacte, sinon sous-route (ex. billing.quote.compute), sinon même module.
  $navRoutes = collect($navGroups)->flatMap(fn ($g) => array_keys($g));
  $currentRoute = (string) request()->route()?->getName();
  $activeNav = $navRoutes->first(fn ($r) => $r === $currentRoute)
      ?? $navRoutes->first(fn ($r) => str_starts_with($currentRoute, $r . '.'))
      ?? $navRoutes->first(fn ($r) => \Illuminate\Support\Str::beforeLast($r, '.') === \Illuminate\Support\Str::before($currentRoute, '.'));
  $user = auth()->user();
  // Navigation affichée seulement une fois la connexion complète (double authentification comprise).
  $showNav = $user && ! request()->routeIs('tenant.mfa.*');
  $roleLabels = \App\Support\TenantNav::ROLE_LABELS;
  $roleName = $user && method_exists($user, 'getRoleNames') ? ($roleLabels[$user->getRoleNames()->first()] ?? ucfirst(str_replace('_', ' ', (string) $user->getRoleNames()->first()))) : null;
  $mutualCurrency = null;
  try { $mutualCurrency = \App\Models\Central\Currency::on('central')->find(tenant('currency_id')); } catch (\Throwable $e) {}
  $version = @file_get_contents(base_path('VERSION')) ?: null;
  $base = rtrim(config('app.url'), '/');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'MX-CONNECT' }} · {{ tenant('name') ?? 'Mutuelle' }}</title>
    <link rel="icon" href="{{ $base }}/brand/favicon.png" type="image/png">
    <link rel="preload" href="{{ $base }}/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ $base }}/css/mx-tenant.css?v={{ config('mxconnect.asset_version') }}">
    <style>
      @font-face{font-family:"Inter";font-style:normal;font-display:swap;font-weight:100 900;src:url({{ $base }}/fonts/inter-latin.woff2) format("woff2")}
      .mx-side::-webkit-scrollbar{width:6px}.mx-side::-webkit-scrollbar-thumb{background:rgba(255,255,255,.15);border-radius:6px}
      #mx-nav-toggle:checked ~ .mx-shell .mx-side{transform:translateX(0)}
      #mx-nav-toggle:checked ~ .mx-shell .mx-scrim{display:block}
      @media (prefers-reduced-motion: reduce){*{transition:none!important;animation:none!important}}
    </style>
    {{ $head ?? '' }}
</head>
<body class="min-h-screen bg-paper text-ink antialiased font-sans">
  <input type="checkbox" id="mx-nav-toggle" class="hidden" aria-hidden="true">
  <div class="mx-shell min-h-screen lg:pl-64 print:pl-0">
    {{-- Barre latérale --}}
    <label for="mx-nav-toggle" class="mx-scrim fixed inset-0 z-30 hidden bg-navy-deep/50 lg:hidden" aria-hidden="true"></label>
    <aside class="mx-side print:hidden fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto bg-gradient-to-b from-[#0B2A5B] via-[#08234D] to-[#061A3A] text-white transition-transform duration-200 lg:translate-x-0" aria-label="Navigation du back-office">
      <div class="flex h-16 items-center gap-3 px-5 border-b border-white/10">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-white shadow"><img src="{{ $base }}/brand/mx-mark-128.webp" alt="" class="h-8 w-8"></span>
        <div class="leading-tight min-w-0">
          <div class="font-extrabold tracking-wide text-[15px]">MX<span class="text-[#7BD389]">-</span>CONNECT</div>
          <div class="truncate text-[11px] text-white/60">{{ tenant('name') ?? 'Mutuelle' }}</div>
        </div>
      </div>
      @if ($showNav)
      <nav class="px-3 py-4 space-y-5 text-[13.5px]">
        @foreach ($navGroups as $groupLabel => $items)
          @php
            $visible = collect($items)->filter(fn ($v, $r) => Route::has($r) && \App\Support\TenantAccess::can($r));
          @endphp
          @if ($visible->isNotEmpty())
            <div>
              <div class="px-3 pb-1.5 text-[10.5px] font-semibold uppercase tracking-[.12em] text-white/40">{{ $groupLabel }}</div>
              <ul class="space-y-0.5">
                @foreach ($visible as $r => [$label, $icon])
                  @php $on = $r === $activeNav; @endphp
                  <li>
                    <a href="{{ route($r) }}" @if($on) aria-current="page" @endif
                       class="group flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ $on ? 'bg-gradient-to-r from-leaf-500 to-leaf-600 text-white shadow-lg shadow-black/20' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                      <span class="{{ $on ? 'text-white' : 'text-white/55 group-hover:text-white' }}">{!! $svg($icon, 19) !!}</span>
                      <span class="flex-1">{{ __($label) }}</span>
                      @if (! empty($navAlerts[$r]))<span class="rounded-full bg-[#E5484D] px-1.5 py-0.5 text-[10.5px] font-bold leading-none" title="À traiter">{{ $navAlerts[$r] }}</span>@endif
                    </a>
                  </li>
                @endforeach
              </ul>
            </div>
          @endif
        @endforeach
      </nav>
      @endif
      <div class="m-3 mt-2 rounded-2xl bg-white/[.06] p-4 ring-1 ring-white/10">
        <div class="flex items-center gap-2 text-sm font-semibold">{!! $svg('lock', 16) !!} Données protégées</div>
        <p class="mt-1 text-[11.5px] leading-relaxed text-white/60">Base de données propre à la mutuelle, accès par rôles et journal d’audit.</p>
      </div>
    </aside>

    <div class="flex min-h-screen flex-col">
      {{-- En-tête --}}
      <header class="print:hidden sticky top-0 z-20 border-b border-ink/[.06] bg-white/85 backdrop-blur">
        <div class="flex h-16 items-center gap-3 px-4 sm:px-6">
          <label for="mx-nav-toggle" class="grid h-10 w-10 cursor-pointer place-items-center rounded-xl text-ink/70 hover:bg-ink/5 lg:hidden" aria-label="Ouvrir le menu">{!! $svg('menu', 22) !!}</label>
          @if ($showNav)
            @if (Route::has('members.index') && \App\Support\TenantAccess::can('members.index'))
              <form action="{{ route('members.index') }}" method="GET" role="search" class="relative hidden max-w-md flex-1 sm:block">
                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-ink/40">{!! $svg('search', 18) !!}</span>
                <label for="mx-tsearch" class="sr-only">Rechercher un adhérent</label>
                <input id="mx-tsearch" name="q" value="{{ request()->routeIs('members.index') ? request('q') : '' }}" placeholder="Rechercher un adhérent (nom, code, téléphone)…"
                       class="h-10 w-full rounded-full border border-ink/10 bg-paper pl-10 pr-4 text-sm outline-none transition focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-100">
              </form>
            @endif
          @endif
          <div class="ml-auto flex items-center gap-2 sm:gap-3">
            @if ($mutualCurrency)
              <span class="hidden items-center gap-1.5 rounded-full border border-leaf-100 bg-leaf-50 px-3 py-1.5 text-xs font-semibold text-leaf-700 md:inline-flex" title="Devise de la mutuelle : {{ $mutualCurrency->name }}">
                <span class="grid h-5 w-5 place-items-center rounded-full bg-leaf-500 text-[10px] text-white">{{ mb_substr($mutualCurrency->symbol, 0, 1) }}</span>{{ $mutualCurrency->symbol }} · {{ $mutualCurrency->code }}
              </span>
            @endif
            @auth
              <div class="flex items-center gap-3 rounded-full py-1 pl-1 pr-1 sm:pr-3">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-navy text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <span class="hidden leading-tight sm:block"><span class="block text-sm font-semibold">{{ $user->name }}</span>@if($roleName)<span class="block text-[11.5px] text-ink/50">{{ $roleName }}</span>@endif</span>
              </div>
              @if (Route::has('account.password'))<a href="{{ route('account.password') }}" class="grid h-10 w-10 place-items-center rounded-xl text-ink/60 hover:bg-mist" title="Mon mot de passe" aria-label="Mon mot de passe">{!! $svg('lock', 18) !!}</a>@endif
              <form method="POST" action="{{ url('/deconnexion') }}">
                @csrf
                <button class="grid h-10 w-10 place-items-center rounded-xl text-clay hover:bg-clay/10" title="{{ __('mxconnect.auth.logout') }}" aria-label="{{ __('mxconnect.auth.logout') }}">{!! $svg('logout', 19) !!}</button>
              </form>
            @endauth
          </div>
        </div>
      </header>

      <div class="px-4 sm:px-6">
        @foreach (['success' => 'leaf', 'error' => 'clay'] as $key => $c)
          @if (session($key))
            <div role="status" class="mt-4 rounded-xl border px-4 py-3 text-sm {{ $c === 'leaf' ? 'border-leaf-100 bg-leaf-50 text-leaf-700' : 'border-clay/30 bg-clay/5 text-clay' }}">{{ session($key) }}</div>
          @endif
        @endforeach
      </div>

      <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 sm:py-8">{{ $slot }}</main>

      <footer class="print:hidden border-t border-ink/[.06] bg-white/70">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-4 text-xs text-ink/50 sm:px-6">
          <span class="flex items-center gap-2"><img src="{{ $base }}/brand/mx-mark-128.webp" alt="" class="h-5 w-5"><b class="text-ink/70">MX-CONNECT</b> · Plateforme de gestion des mutuelles de santé</span>
          <span class="flex flex-wrap items-center gap-4">
            <span class="flex items-center gap-1.5">{!! $svg('lock', 14) !!} Accès sécurisé par rôles</span>
            <span class="flex items-center gap-1.5">{!! $svg('history', 14) !!} Journal d’audit</span>
            @if ($version)<span>Version {{ trim($version) }}</span>@endif
          </span>
        </div>
      </footer>
    </div>
  </div>
  {{ $scripts ?? '' }}
</body>
</html>
