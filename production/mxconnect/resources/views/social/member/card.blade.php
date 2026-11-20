<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ __('Ma carte de membre · MX-CONNECT') }}</title>
  <link rel="stylesheet" href="{{ rtrim(config('app.url'), '/') }}/css/mx-tenant.css?v={{ config('mxconnect.asset_version') }}">
  <style>
    .mx-card svg[width]{width:100%!important;height:100%!important}
    .wrap{max-width:420px;margin:0 auto}
    .scale{transform-origin:top left}
    @media (max-width:360px){.scale{transform:scale(.92)}}
    @media print{.no-print{display:none}body{background:#fff;padding:0}.mx-card{box-shadow:none!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}}
  </style>
</head>
<body class="bg-paper p-4 font-sans text-ink">
  <div class="wrap">
    <div class="no-print mb-4 flex flex-wrap gap-2">
      <button onclick="window.print()" class="rounded-xl bg-pine px-4 py-2.5 text-sm font-bold text-white">{{ __('Imprimer ou enregistrer en PDF') }}</button>
      <a href="{{ route('social.member.dashboard', $mutual->slug) }}" class="rounded-xl border border-ink/10 bg-white px-4 py-2.5 text-sm font-semibold text-ink/70">{{ __('Retour') }}</a>
    </div>
    @if ($sub)
      <div class="scale">@include('tenant.card.partials.card', ['member' => $m, 'subscription' => $sub, 'token' => $token, 'verifyUrl' => $verifyUrl])</div>
      <p class="no-print mt-4 text-xs text-ink/60">{{ __('Présentez cette carte (ou ce QR code) à l’établissement de soins : il vérifie vos droits en direct auprès de la mutuelle. Le QR code ne contient aucune donnée médicale.') }}</p>
    @else
      <p class="rounded-xl bg-white p-4 text-sm">{{ __('Aucune formule validée : la carte sera disponible quand votre adhésion sera active.') }}</p>
    @endif
  </div>
</body>
</html>
