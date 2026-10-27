@props(['title' => null, 'class' => ''])
{{-- Écrans plein écran de l'application (démarrage, onboarding, connexion, conditions) — 2026-11-09 --}}
<!DOCTYPE html>
<html lang="fr" class="is-app-screen">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#FFFFFF">
  <meta name="robots" content="noindex">
  <title>{{ $title ? $title . ' · ' : '' }}MX-CONNECT</title>
  <link rel="icon" href="/brand/favicon.png" type="image/png">
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="preload" href="/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="/css/mx-social.css?v={{ config('mxconnect.asset_version') }}">
  <link rel="stylesheet" href="/css/mx-app.css?v={{ config('mxconnect.asset_version') }}">
  {{ $head ?? '' }}
</head>
<body class="mx-appscreen">
  <main {{ $attributes->merge(['class' => 'mx-as ' . $class]) }}>
    {{ $slot }}
  </main>
  {{ $scripts ?? '' }}
</body>
</html>
