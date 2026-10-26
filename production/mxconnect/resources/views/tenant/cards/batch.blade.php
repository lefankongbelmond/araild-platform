<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Impression de {{ $members->count() }} carte(s) de membre</title>
  <link rel="stylesheet" href="{{ rtrim(config('app.url'), '/') }}/css/mx-tenant.css?v={{ config('mxconnect.asset_version') }}">
  <style>
    .mx-card svg[width]{width:100%!important;height:100%!important}
    @page{size:A4;margin:10mm}
    .sheet{display:grid;grid-template-columns:repeat(2,85.6mm);gap:6mm 8mm;justify-content:center}
    .sheet>div{break-inside:avoid}
    @media print{.no-print{display:none}body{background:#fff;padding:0}.mx-card{box-shadow:none!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}}
  </style>
</head>
<body class="bg-paper p-6 font-sans text-ink">
  <div class="no-print mx-auto mb-6 flex max-w-3xl flex-wrap items-center justify-between gap-3 rounded-2xl bg-white p-4 shadow-soft ring-1 ring-ink/[.05]">
    <div><b class="text-[#0B1F3A]">{{ $members->count() }} carte(s) prête(s)</b><div class="text-xs text-ink/55">Format carte bancaire, 10 par page A4. Activez « Graphiques d’arrière-plan » dans la fenêtre d’impression.</div></div>
    <div class="flex gap-2">
      <button onclick="window.print()" class="rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-4 py-2.5 text-sm font-bold text-white">Imprimer</button>
      <form method="POST" action="{{ route('cards.printed') }}">@csrf @foreach ($members as $m)<input type="hidden" name="ids[]" value="{{ $m->id }}">@endforeach
        <button class="rounded-xl border border-ink/10 bg-white px-4 py-2.5 text-sm font-semibold text-ink/70">Marquer comme imprimées</button></form>
    </div>
  </div>
  <div class="sheet">
    @foreach ($members as $m)
      <div>@include('tenant.card.partials.card', ['member' => $m, 'subscription' => $m->subscriptions->first(), 'token' => $tokens[$m->id]])</div>
    @endforeach
  </div>
</body>
</html>
