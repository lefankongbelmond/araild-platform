<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ __('mxconnect.card.title') }} — {{ $member->member_code }}</title>
  <link rel="stylesheet" href="{{ rtrim(config('app.url'), '/') }}/css/mx-tenant.css?v={{ config('mxconnect.asset_version') }}">
  <style>.mx-card svg[width]{width:100%!important;height:100%!important} @page{size:A4;margin:12mm} @media print{.no-print{display:none}}</style>
</head>
<body class="min-h-screen bg-paper p-6 font-sans text-ink">
  <div class="mx-auto flex max-w-md flex-col items-center">
    @include('tenant.card.partials.card', ['member' => $member, 'subscription' => $subscription, 'token' => $token])
    <p class="mt-4 text-center text-xs text-ink/50">{{ __('mxconnect.card.no_medical') }}</p>
    <div class="no-print mt-4 flex gap-2">
      <button onclick="window.print()" class="rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-5 py-2.5 text-sm font-bold text-white">{{ __('mxconnect.card.print') }}</button>
      <a href="{{ route('cards.index') }}" class="rounded-xl border border-ink/10 bg-white px-5 py-2.5 text-sm font-semibold text-ink/70">Cartes de membre</a>
    </div>
  </div>
</body>
</html>
