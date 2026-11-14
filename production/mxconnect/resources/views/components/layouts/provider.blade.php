@props(['title' => 'Espace prestataire', 'bare' => false])
@php
  // Espace prestataire central (2026-11-16) : même identité visuelle que le portail des mutuelles
  // (barre latérale marine, élément actif vert, cartes arrondies). Menu filtré par les droits de la
  // personne DANS l'établissement (App\Support\ProviderAccess) ; seules les rubriques réellement
  // disponibles sont affichées.
  $base = rtrim(config('app.url'), '/');
  $m = $bare ? null : \App\Support\ProviderAccess::current();
  $account = auth('provider')->user();
  $route = (string) request()->route()?->getName();
  $target = request()->route('target');
  $nav = [
    'Portail prestataire' => [
      ['pro.dashboard', [], 'Tableau de bord', 'chart', null],
      ['pro.go', ['verify'], 'Vérifier les droits', 'shield', 'rights.verify'],
      ['pro.go', ['claims'], 'Prises en charge', 'clipboard', 'claims.view'],
      ['pro.go', ['invoices'], 'Mes factures', 'receipt', 'invoices.view'],
      ['pro.mutuals', [], 'Conventions et tarifs', 'book', 'conventions.view'],
    ],
    'Gestion de l’établissement' => [
      ['pro.profile', [], 'Profil de l’établissement', 'stethoscope', null],
      ['pro.staff', [], 'Personnel et utilisateurs', 'users', 'staff.manage'],
      ['pro.documents', [], 'Agréments et documents', 'file', 'conventions.view'],
      ['pro.settings', [], 'Paramètres', 'lock', null],
    ],
  ];
  $isActive = function ($r, $params) use ($route, $target) {
      if ($r === 'pro.go') { return $route === 'pro.go' && $target === ($params[0] ?? null); }
      if ($r === 'pro.mutuals') { return str_starts_with($route, 'pro.mutuals'); }
      return $route === $r;
  };
  $version = @file_get_contents(base_path('VERSION')) ?: null;
  $memberships = $providerMemberships ?? collect();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · MX-CONNECT Prestataire</title>
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
</head>
<body class="min-h-screen bg-paper text-ink antialiased font-sans">
@if ($bare)
  {{-- Connexion, inscription, double authentification : page simple centrée. --}}
  <div class="flex min-h-screen flex-col">
    <header class="border-b border-ink/[.06] bg-white">
      <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 sm:px-6">
        <a href="{{ $base }}/" class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-white shadow ring-1 ring-ink/[.05]"><img src="{{ $base }}/brand/mx-mark-128.webp" alt="" class="h-8 w-8"></span>
          <span class="leading-tight"><span class="block font-extrabold tracking-wide text-[15px] text-[#0B1F3A]">MX<span class="text-leaf-600">-</span>CONNECT</span><span class="block text-[11px] text-ink/55">Espace prestataire de soins</span></span></a>
        @if ($account)
          <form method="POST" action="{{ route('pro.logout') }}" class="ml-auto">@csrf<button class="rounded-xl px-3 py-2 text-sm text-clay hover:bg-clay/10">Se déconnecter</button></form>
        @endif
      </div>
    </header>
    <div class="mx-auto w-full max-w-6xl px-4 sm:px-6">
      @foreach (['success' => 'leaf', 'error' => 'clay'] as $key => $c)
        @if (session($key))<div role="status" class="mt-4 rounded-xl border px-4 py-3 text-sm {{ $c === 'leaf' ? 'border-leaf-100 bg-leaf-50 text-leaf-700' : 'border-clay/30 bg-clay/5 text-clay' }}">{{ session($key) }}</div>@endif
      @endforeach
    </div>
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">{{ $slot }}</main>
    <footer class="border-t border-ink/[.06] bg-white/70"><div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4 text-xs text-ink/50 sm:px-6">
      <span class="flex items-center gap-2"><img src="{{ $base }}/brand/mx-mark-128.webp" alt="" class="h-5 w-5"><b class="text-ink/70">MX-CONNECT</b> · Espace prestataire</span>
      <span class="flex flex-wrap items-center gap-4"><span class="flex items-center gap-1.5"><x-tenant.icon name="lock" :size="14"/> Double authentification obligatoire</span>@if ($version)<span>Version {{ trim($version) }}</span>@endif</span>
    </div></footer>
  </div>
@else
  <input type="checkbox" id="mx-nav-toggle" class="hidden" aria-hidden="true">
  <div class="mx-shell min-h-screen lg:pl-64 print:pl-0">
    <label for="mx-nav-toggle" class="mx-scrim fixed inset-0 z-30 hidden bg-navy-deep/50 lg:hidden" aria-hidden="true"></label>
    <aside class="mx-side print:hidden fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto bg-gradient-to-b from-[#0B2A5B] via-[#08234D] to-[#061A3A] text-white transition-transform duration-200 lg:translate-x-0" aria-label="Navigation de l’espace prestataire">
      <div class="flex h-16 items-center gap-3 px-5 border-b border-white/10">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-white shadow"><img src="{{ $base }}/brand/mx-mark-128.webp" alt="" class="h-8 w-8"></span>
        <div class="leading-tight min-w-0">
          <div class="font-extrabold tracking-wide text-[15px]">MX<span class="text-[#7BD389]">-</span>CONNECT</div>
          <div class="truncate text-[11px] text-white/60">{{ $m?->provider?->name ?? 'Espace prestataire' }}</div>
        </div>
      </div>
      <nav class="px-3 py-4 space-y-5 text-[13.5px]">
        @foreach ($nav as $group => $items)
          @php $visible = collect($items)->filter(fn ($i) => $i[4] === null || \App\Support\ProviderAccess::allowed($i[4], $m)); @endphp
          @if ($visible->isNotEmpty())
            <div>
              <div class="px-3 pb-1.5 text-[10.5px] font-semibold uppercase tracking-[.12em] text-white/40">{{ $group }}</div>
              <ul class="space-y-0.5">
                @foreach ($visible as [$r, $params, $label, $icon])
                  @php $on = $isActive($r, $params); @endphp
                  <li><a href="{{ route($r, $params) }}" @if($on) aria-current="page" @endif
                       class="group flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ $on ? 'bg-gradient-to-r from-leaf-500 to-leaf-600 text-white shadow-lg shadow-black/20' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                      <span class="{{ $on ? 'text-white' : 'text-white/55 group-hover:text-white' }}"><x-tenant.icon :name="$icon" :size="19"/></span>
                      <span class="flex-1">{{ $label }}</span></a></li>
                @endforeach
              </ul>
            </div>
          @endif
        @endforeach
      </nav>
      <div class="m-3 mt-2 rounded-2xl bg-white/[.06] p-4 ring-1 ring-white/10">
        <div class="flex items-center gap-2 text-sm font-semibold"><x-tenant.icon name="lock" :size="16"/> Données protégées</div>
        <p class="mt-1 text-[11.5px] leading-relaxed text-white/60">Les soins et les adhérents restent dans la base de chaque mutuelle. Accès par rôles, double authentification et journal d’audit.</p>
      </div>
    </aside>

    <div class="flex min-h-screen flex-col">
      <header class="print:hidden sticky top-0 z-20 border-b border-ink/[.06] bg-white/85 backdrop-blur">
        <div class="flex h-16 items-center gap-3 px-4 sm:px-6">
          <label for="mx-nav-toggle" class="grid h-10 w-10 cursor-pointer place-items-center rounded-xl text-ink/70 hover:bg-ink/5 lg:hidden" aria-label="Ouvrir le menu"><x-tenant.icon name="menu" :size="22"/></label>
          <div class="ml-auto flex items-center gap-2 sm:gap-3">
            @if ($account)
              <div class="flex items-center gap-3 rounded-full py-1 pl-1 pr-1 sm:pr-3">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-navy text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($account->first_name, 0, 1)) }}</span>
                <span class="hidden leading-tight sm:block"><span class="block text-sm font-semibold">{{ $account->name }}</span>@if($m)<span class="block text-[11.5px] text-ink/50">{{ $m->roleLabel() }}</span>@endif</span>
              </div>
              <a href="{{ route('pro.settings') }}" class="grid h-10 w-10 place-items-center rounded-xl text-ink/60 hover:bg-mist" title="Paramètres du compte" aria-label="Paramètres du compte"><x-tenant.icon name="lock" :size="18"/></a>
              <form method="POST" action="{{ route('pro.logout') }}">@csrf
                <button class="grid h-10 w-10 place-items-center rounded-xl text-clay hover:bg-clay/10" title="Se déconnecter" aria-label="Se déconnecter"><x-tenant.icon name="logout" :size="19"/></button></form>
            @endif
          </div>
        </div>
      </header>

      <div class="px-4 sm:px-6">
        @foreach (['success' => 'leaf', 'error' => 'clay'] as $key => $c)
          @if (session($key))<div role="status" class="mt-4 rounded-xl border px-4 py-3 text-sm {{ $c === 'leaf' ? 'border-leaf-100 bg-leaf-50 text-leaf-700' : 'border-clay/30 bg-clay/5 text-clay' }}">{{ session($key) }}</div>@endif
        @endforeach
        @if ($memberships->count() > 1)
          <form method="POST" action="{{ route('pro.switch') }}" class="mt-3 max-w-md">@csrf
            <label for="mx-est-m" class="text-xs text-ink/50">Établissement</label>
            <select id="mx-est-m" name="membership" onchange="this.form.submit()" class="mt-1 h-10 w-full rounded-xl border border-ink/10 bg-white px-3 text-sm">
              @foreach ($memberships as $ms)<option value="{{ $ms->id }}" @selected($ms->id === $m?->id)>{{ $ms->provider?->name }}</option>@endforeach
            </select>
          </form>
        @endif
      </div>

      <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 sm:py-8">{{ $slot }}</main>

      <footer class="print:hidden border-t border-ink/[.06] bg-white/70">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-4 text-xs text-ink/50 sm:px-6">
          <span class="flex items-center gap-2"><img src="{{ $base }}/brand/mx-mark-128.webp" alt="" class="h-5 w-5"><b class="text-ink/70">MX-CONNECT</b> · Espace prestataire de soins</span>
          <span class="flex flex-wrap items-center gap-4">
            <span class="flex items-center gap-1.5"><x-tenant.icon name="lock" :size="14"/> Accès par rôles</span>
            <span class="flex items-center gap-1.5"><x-tenant.icon name="history" :size="14"/> Journal d’audit</span>
            @if ($version)<span>Version {{ trim($version) }}</span>@endif
          </span>
        </div>
      </footer>
    </div>
  </div>
@endif
{{ $scripts ?? '' }}
</body>
</html>
