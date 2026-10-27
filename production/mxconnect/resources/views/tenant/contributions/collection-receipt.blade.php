<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reçu {{ $number }}</title>
<style>body{font-family:system-ui,sans-serif;color:#0B1F3A;max-width:640px;margin:2rem auto;padding:0 1rem}
.head{display:flex;justify-content:space-between;gap:1rem;border-bottom:2px solid #0B2559;padding-bottom:.5rem}
table{width:100%;border-collapse:collapse;margin-top:1rem}td,th{padding:.45rem 0;border-bottom:1px solid #eee;text-align:left}
.r{text-align:right}.total td{font-weight:600;font-size:1.05rem;border-bottom:0}.muted{color:#6b7280;font-size:.85rem}
@media print{button{display:none}}</style></head>
<body>
  <div class="head">
    <div><strong>{{ $mutual }}</strong><br><span class="muted">Mutuelle de santé · MX-CONNECT</span></div>
    <div class="r"><strong>Reçu de cotisation</strong><br>{{ $number }}<br>{{ $day->format('d/m/Y') }}</div>
  </div>
  <table>
    <tr><td>Adhérent</td><td class="r">{{ $member->last_name }} {{ $member->first_name }} ({{ $member->member_code }})</td></tr>
    <tr><td>Mode</td><td class="r">{{ $mode }}@if ($reference) — réf. {{ $reference }}@endif</td></tr>
  </table>
  <table>
    <thead><tr><th>Période réglée</th><th class="r">Montant</th></tr></thead>
    <tbody>
      @foreach ($payments as $p)
        <tr><td>{{ $p->schedule?->period }}@if ($p->amount_minor < $p->schedule?->due_minor) <span class="muted">(acompte)</span>@endif</td><td class="r">{{ $currency->format($p->amount_minor) }}</td></tr>
      @endforeach
      <tr class="total"><td>Total reçu</td><td class="r">{{ $currency->format($amount) }}</td></tr>
    </tbody>
  </table>
  <p class="muted">Reste à payer sur l’échéancier après ce paiement : {{ $currency->format($balance) }}.</p>
  <p class="muted" style="margin-top:2rem">Reçu généré par MX-CONNECT pour {{ $mutual }}. La plateforme ne détient aucun fonds : la somme a été reçue par la mutuelle.</p>
  <button onclick="window.print()">Imprimer</button>
</body></html>
