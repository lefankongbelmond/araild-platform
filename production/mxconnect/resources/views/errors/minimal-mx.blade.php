<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} · MX-CONNECT</title>
<style>
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:#F4F6FA;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#344054;padding:24px;box-sizing:border-box}
  .c{max-width:440px;background:#fff;border:1px solid #E4E8F0;border-radius:18px;padding:28px;text-align:center;box-shadow:0 2px 8px rgba(16,24,40,.05)}
  h1{color:#102A56;font-size:22px;margin:12px 0 8px} p{line-height:1.55;margin:0 0 16px}
  a.b{display:inline-block;background:#1769D2;color:#fff;text-decoration:none;font-weight:600;padding:10px 18px;border-radius:999px}
  .i{font-size:40px}
</style></head>
<body><main class="c"><div class="i">{{ $icon }}</div><h1>{{ $title }}</h1><p>{{ $text }}</p>
<a class="b" href="javascript:location.reload()">Réessayer</a>
<p style="margin-top:14px;font-size:13px"><a href="/" style="color:#667085">Retour à l’accueil</a></p></main>
@if (! empty($retry))<script>setTimeout(function () { location.reload(); }, {{ (int) $retry * 1000 }});</script>@endif
</body></html>
