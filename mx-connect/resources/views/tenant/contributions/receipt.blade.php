<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $number }}</title>
<style>body{font-family:system-ui,sans-serif;color:#0f2e2b;max-width:640px;margin:2rem auto;padding:0 1rem}
.head{display:flex;justify-content:space-between;border-bottom:2px solid #12433d;padding-bottom:.5rem}
table{width:100%;border-collapse:collapse;margin-top:1rem}td{padding:.4rem 0;border-bottom:1px solid #eee}
.total{font-weight:600;font-size:1.1rem}</style></head>
<body>
  <div class="head">
    <div><strong>MX-CONNECT</strong><br>{{ $mutual }}</div>
    <div style="text-align:right"><strong>{{ __('mxconnect.contribution.receipt') }}</strong><br>{{ $number }}<br>{{ now()->format('d/m/Y') }}</div>
  </div>
  <table>
    <tr><td>{{ __('mxconnect.member.name') }}</td><td style="text-align:right">{{ $payment->schedule?->subscription?->member?->last_name }} {{ $payment->schedule?->subscription?->member?->first_name }}</td></tr>
    <tr><td>{{ __('mxconnect.contribution.period') }}</td><td style="text-align:right">{{ $payment->schedule?->period }}</td></tr>
    <tr><td>{{ __('mxconnect.contribution.mode') }}</td><td style="text-align:right">{{ $payment->mode }}</td></tr>
    <tr><td>{{ __('mxconnect.contribution.paid_on') }}</td><td style="text-align:right">{{ optional($payment->paid_on)->format('d/m/Y') }}</td></tr>
    <tr class="total"><td>{{ __('mxconnect.contribution.amount') }}</td><td style="text-align:right">{{ $currency->format($payment->amount_minor) }}</td></tr>
  </table>
  <p style="margin-top:2rem;font-size:.8rem;color:#888">{{ __('mxconnect.contribution.receipt_note') }}</p>
</body></html>
