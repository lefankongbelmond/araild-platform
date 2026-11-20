{{--
  Sélecteur de langue (M1 multilingue, 2026-11-27). Aucun JavaScript : <details> + liens.
  Styles autonomes (préfixe .mx-lng) : il s'insère dans tous les gabarits (réseau social,
  mutuelle, prestataire, super-admin, accueil). Une langue dont la traduction est incomplète est
  signalée honnêtement ; le reste du texte s'affiche alors en français.
  Props : variant = "menu" (liste déroulante) | "list" (liste à plat, dans un tiroir ou une page).
--}}
@props(['variant' => 'menu', 'up' => false])
@php
  $langs = \App\Support\Locales::active();
  $cur = app()->getLocale();
  $onTenant = function_exists('tenancy') && tenancy()->initialized;
  $url = fn ($c) => $onTenant ? route('tenant.lang.switch', $c) : route('lang.switch', $c);
  $partial = fn ($c) => ! \App\Support\Locales::isComplete($c);
@endphp
@if (count($langs) > 1)
  @once
    <style>
      .mx-lng{position:relative;display:inline-block;font-size:14px;line-height:1.3}
      .mx-lng>summary{list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid rgba(15,46,43,.15);border-radius:999px;background:#fff;color:#0B2559;font-weight:600;white-space:nowrap}
      .mx-lng>summary::-webkit-details-marker{display:none}
      .mx-lng>summary:focus-visible{outline:2px solid #2F6FDB;outline-offset:2px}
      .mx-lng__list{position:absolute;inset-inline-end:0;top:calc(100% + 6px);z-index:80;min-width:220px;max-width:calc(100vw - 32px);background:#fff;border:1px solid rgba(15,46,43,.12);border-radius:12px;box-shadow:0 12px 32px rgba(11,37,89,.16);padding:6px;margin:0;list-style:none}
      .mx-lng--up .mx-lng__list{top:auto;bottom:calc(100% + 6px)}
      .mx-lng__list a,.mx-lngl a{display:flex;justify-content:space-between;gap:10px;padding:8px 10px;border-radius:8px;color:#0f2e2b;text-decoration:none}
      .mx-lng__list a:hover,.mx-lngl a:hover{background:#eef3f1}
      .mx-lng__list a[aria-current],.mx-lngl a[aria-current]{background:#e8f5ec;color:#1f7a3c;font-weight:700}
      .mx-lng small,.mx-lngl small{color:#6b7c7a;font-weight:400;font-size:12px}
      .mx-lngl{display:grid;gap:2px;margin:0;padding:0;list-style:none}
    </style>
  @endonce
  @if ($variant === 'list')
    <ul class="mx-lngl" aria-label="{{ __('Langue de l’interface') }}">
      @foreach ($langs as $c)
        <li><a href="{{ $url($c) }}" hreflang="{{ $c }}" @if ($c === $cur) aria-current="true" @endif><span lang="{{ $c }}" dir="{{ \App\Support\Locales::direction($c) }}">{{ \App\Support\Locales::name($c) }}</span>@if ($partial($c))<small>{{ __('traduction partielle') }}</small>@endif</a></li>
      @endforeach
    </ul>
  @else
    <details {{ $attributes->class(['mx-lng', 'mx-lng--up' => $up]) }}>
      <summary aria-label="{{ __('Langue de l’interface') }} : {{ \App\Support\Locales::name($cur) }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/></svg>
        <span>{{ strtoupper($cur) }}</span>
      </summary>
      <ul class="mx-lng__list">
        @foreach ($langs as $c)
          <li><a href="{{ $url($c) }}" hreflang="{{ $c }}" @if ($c === $cur) aria-current="true" @endif><span lang="{{ $c }}" dir="{{ \App\Support\Locales::direction($c) }}">{{ \App\Support\Locales::name($c) }}</span>@if ($partial($c))<small>{{ __('traduction partielle') }}</small>@endif</a></li>
        @endforeach
      </ul>
    </details>
  @endif
@endif
