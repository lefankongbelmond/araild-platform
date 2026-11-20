{{-- Gabarit des documents imprimables du portail adhérent (attestation, reçu). --}}
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $docTitle }} · MX-CONNECT</title>
  <link rel="stylesheet" href="{{ rtrim(config('app.url'), '/') }}/css/mx-social.css?v={{ config('mxconnect.asset_version') }}">
  <style>
    body{background:#F4F6FA;margin:0;padding:16px;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#0B1F3A}
    .doc{max-width:760px;margin:0 auto;background:#fff;border-radius:14px;padding:28px;box-shadow:0 2px 10px rgba(11,37,89,.08)}
    .doc h1{font-size:22px;margin:4px 0 2px} .doc table{width:100%;border-collapse:collapse;margin-top:12px;font-size:14px}
    .doc td,.doc th{border-bottom:1px solid #E3E8F0;padding:8px 6px;text-align:left;vertical-align:top}
    .bar{max-width:760px;margin:0 auto 12px;display:flex;gap:8px;flex-wrap:wrap}
    @media print{.bar{display:none}body{background:#fff;padding:0}.doc{box-shadow:none;border-radius:0}}
  </style>
</head>
<body>
  <div class="bar"><button class="mx-btn mx-btn--primary" onclick="window.print()">{{ __('Imprimer ou enregistrer en PDF') }}</button><a class="mx-btn mx-btn--ghost" href="{{ $back }}">{{ __('Retour') }}</a></div>
  <div class="doc">{{ $slot ?? '' }}@yield('doc')</div>
</body>
</html>
