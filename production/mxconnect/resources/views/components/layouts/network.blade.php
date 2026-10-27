@php
  // Espace super-admin du réseau (refonte 2026-11-02) : barre latérale groupée, menu mobile,
  // CSS compilé (css/mx-network.css) au lieu du Tailwind CDN.
  $u = auth('network')->user();
  $roles = $u ? $u->getRoleNames()->all() : [];
  $is = fn (array $r) => (bool) array_intersect($r, $roles);
  $SA = ['super_admin']; $ADM = ['super_admin', 'security_admin']; $MOD = ['super_admin', 'moderator'];
  $groups = [
      'Mutuelles' => [
          ['network.mutuals.index', 'Mutuelles', 'building', $ADM, 'network.mutuals.*'],
          ['network.health.index', 'Santé des espaces', 'pulse', $SA, 'network.health.*'],
          ['network.consolidation.index', 'Consolidation', 'chart', $ADM, 'network.consolidation.*'],
      ],
      'Commercial' => [
          ['network.billing.index', 'Facturation', 'receipt', $SA, 'network.billing.index'],
          ['network.billing.plans', 'Plans et tarifs', 'tag', $SA, 'network.billing.plans*'],
          ['network.demos.index', 'Demandes de démo', 'inbox', $SA, 'network.demos.*'],
      ],
      'Réseau social' => [
          ['network.moderation.index', 'Signalements', 'flag', $MOD, 'network.moderation.index'],
          ['network.moderation.pages', 'Pages des membres', 'file', $MOD, 'network.moderation.pages*'],
          ['network.moderation.accounts', 'Comptes suspendus', 'user-x', $MOD, 'network.moderation.accounts*'],
          ['network.social.sponsorships', 'Sponsorisations', 'megaphone', $MOD, 'network.social.sponsorships*'],
          ['network.social.projects', 'Projets et offres', 'target', $MOD, 'network.social.projects*'],
          ['network.social.settings', 'Réglages du réseau', 'sliders', $MOD, 'network.social.settings*'],
      ],
      'Application Android' => [
          ['network.android.legal', 'Documents légaux', 'file', $SA, 'network.android.legal*'],
          ['network.android.feedback', 'Retours bêta', 'inbox', $MOD, 'network.android.feedback*'],
          ['network.android.settings', 'Réglages de l’application', 'phone', $SA, 'network.android.settings*'],
      ],
      'Configuration' => [
          ['network.config.countries', 'Pays', 'globe', $ADM, 'network.config.countries*'],
          ['network.config.currencies', 'Devises', 'coins', $ADM, 'network.config.currencies*'],
          ['network.config.locales', 'Langues', 'language', $ADM, 'network.config.locales*'],
          ['network.config.providers', 'Paiement mobile', 'phone', $ADM, 'network.config.providers*'],
          ['network.dr.index', 'Plan de reprise (PRA)', 'shield', $SA, 'network.dr.*'],
      ],
  ];
  $icons = [
      'building' => '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1M14 9h1M9 13h1M14 13h1M9 17h1M14 17h1"/>',
      'pulse' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
      'chart' => '<path d="M3 3v18h18M7 15l4-4 3 3 5-6"/>',
      'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><path d="M8 7h8M8 11h8M8 15h5"/>',
      'tag' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
      'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11"/>',
      'flag' => '<path d="M4 22V4a1 1 0 0 1 1-1h12l-2 4 2 4H5"/>',
      'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
      'user-x' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m17 8 5 5M22 8l-5 5"/>',
      'megaphone' => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
      'target' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
      'sliders' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
      'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 0 20M12 2a15.3 15.3 0 0 0 0 20"/>',
      'coins' => '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18M7 6h1v4"/>',
      'language' => '<path d="m5 8 6 6M4 14l6-6 2-3M2 5h12M7 2h1M22 22l-5-10-5 10M14 18h6"/>',
      'phone' => '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>',
      'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>',
      'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
      'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
  ];
  $svg = fn ($n, $s = 19) => '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$n] ?? '') . '</svg>';
  $roleLabels = ['super_admin' => 'Super administrateur', 'security_admin' => 'Administrateur sécurité', 'moderator' => 'Modérateur'];
  $roleLabel = $roleLabels[$roles[0] ?? ''] ?? null;
  $showNav = $u && ! request()->routeIs('mfa.*');
  $version = trim((string) @file_get_contents(base_path('VERSION')));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'MX-CONNECT' }} · Administration du réseau MX-CONNECT</title>
    <link rel="icon" href="/brand/favicon.png" type="image/png">
    <link rel="preload" href="/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/css/mx-network.css?v={{ config('mxconnect.asset_version') }}">
    <style>@font-face{font-family:"Inter";font-style:normal;font-display:swap;font-weight:100 900;src:url(/fonts/inter-latin.woff2) format("woff2")}</style>
</head>
<body class="min-h-screen bg-paper font-sans text-ink antialiased">
@if ($showNav)
  <input type="checkbox" id="nx-nav" class="peer hidden" aria-hidden="true">
  <label for="nx-nav" class="fixed inset-0 z-30 hidden bg-navy-deep/50 peer-checked:block lg:hidden" aria-hidden="true"></label>
  <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-gradient-to-b from-navy to-navy-deep text-white transition-transform duration-200 peer-checked:translate-x-0 lg:translate-x-0" aria-label="Navigation de l’administration du réseau">
    <a href="{{ route('network.mutuals.index') }}" class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
      <span class="grid h-10 w-10 place-items-center rounded-xl bg-white shadow"><img src="/brand/mx-mark-128.webp" alt="" class="h-8 w-8"></span>
      <span class="leading-tight"><span class="block text-[15px] font-extrabold tracking-wide">MX<span class="text-leaf-300">-</span>CONNECT</span>
        <span class="block text-[11px] text-white/60">Administration du réseau</span></span>
    </a>
    <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4 text-[13.5px]">
      @foreach ($groups as $label => $items)
        @php $visible = array_filter($items, fn ($i) => $is($i[3]) && Route::has($i[0])); @endphp
        @if ($visible)
          <div>
            <div class="px-3 pb-1.5 text-[10.5px] font-semibold uppercase tracking-[.12em] text-white/40">{{ $label }}</div>
            <ul class="space-y-0.5">
              @foreach ($visible as [$route, $text, $icon, , $active])
                @php $on = request()->routeIs($active); @endphp
                <li><a href="{{ route($route) }}" @if ($on) aria-current="page" @endif
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ $on ? 'bg-gradient-to-r from-leaf-500 to-leaf-600 text-white shadow-lg shadow-black/20' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                  <span class="{{ $on ? 'text-white' : 'text-white/55' }}">{!! $svg($icon) !!}</span>{{ $text }}</a></li>
              @endforeach
            </ul>
          </div>
        @endif
      @endforeach
    </nav>
    <div class="shrink-0 border-t border-white/10 px-5 py-3 text-[11px] leading-relaxed text-white/50">Accès protégé : double authentification, droits par rôle, journal d’audit.</div>
  </aside>
@endif

  <div class="flex min-h-screen flex-col {{ $showNav ? 'lg:pl-64' : '' }}">
    <header class="sticky top-0 z-20 border-b border-ink/[.06] bg-white/90 backdrop-blur">
      <div class="flex h-16 items-center gap-3 px-4 sm:px-6">
        @if ($showNav)
          <label for="nx-nav" class="grid h-10 w-10 cursor-pointer place-items-center rounded-xl text-ink/70 hover:bg-ink/5 lg:hidden" aria-label="Ouvrir le menu">{!! $svg('menu', 22) !!}</label>
        @else
          <a href="/" class="flex items-center gap-2"><img src="/brand/mx-mark-128.webp" alt="" class="h-8 w-8"><b class="tracking-tight">MX-<span class="text-leaf-600">CONNECT</span></b></a>
        @endif
        <div class="min-w-0 flex-1 truncate text-sm font-semibold text-ink/80">{{ $title ?? '' }}</div>
        @if ($u)
          <div class="flex items-center gap-2 sm:gap-3">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-pine to-navy text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
            <span class="hidden leading-tight sm:block"><span class="block text-sm font-semibold">{{ $u->name }}</span>@if ($roleLabel)<span class="block text-[11.5px] text-ink/50">{{ $roleLabel }}</span>@endif</span>
            <form method="POST" action="{{ route('network.logout') }}">@csrf
              <button class="grid h-10 w-10 place-items-center rounded-xl text-clay hover:bg-clay/10" title="{{ __('mxconnect.auth.logout') }}" aria-label="{{ __('mxconnect.auth.logout') }}">{!! $svg('logout') !!}</button>
            </form>
          </div>
        @endif
      </div>
    </header>

    <div class="px-4 sm:px-6">
      @foreach (['success' => 'ok', 'info' => 'ok', 'warning' => 'warn', 'error' => 'err'] as $key => $tone)
        @if (session($key))
          <div role="status" class="mx-auto mt-4 max-w-7xl rounded-xl border px-4 py-3 text-sm {{ $tone === 'ok' ? 'border-leaf-100 bg-leaf-50 text-leaf-700' : ($tone === 'warn' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-clay/30 bg-clay/5 text-clay') }}">{{ session($key) }}</div>
        @endif
      @endforeach
    </div>

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 sm:py-8">{{ $slot }}</main>

    <footer class="border-t border-ink/[.06] bg-white/70">
      <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs text-ink/50 sm:px-6">
        <span>MX-CONNECT — {{ __('mxconnect.footer') }}</span>
        @if ($version)<span>Version {{ $version }}</span>@endif
      </div>
    </footer>
  </div>
  {{ $scripts ?? '' }}
</body>
</html>
