@php $base = rtrim(config('app.url'), '/'); @endphp
<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>{{ $subjectLine }}</title></head>
<body style="margin:0;background:#F4F7FB;font-family:Arial,Helvetica,sans-serif;color:#172B4D">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F7FB;padding:24px 12px"><tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;border:1px solid #E5EAF2">
      <tr><td style="padding:24px 28px 8px;text-align:center"><img src="{{ $base }}/brand/mx-logo-full.png" width="150" alt="MX-CONNECT" style="display:inline-block;border:0"></td></tr>
      <tr><td style="padding:8px 28px 4px"><h1 style="margin:0;font-size:20px;color:#021F5A">{{ $heading }}</h1></td></tr>
      <tr><td style="padding:8px 28px;font-size:15px;line-height:1.55">
        @foreach ($lines as $line)<p style="margin:0 0 12px">{{ $line }}</p>@endforeach
        @if ($actionUrl)
          <p style="margin:18px 0 6px"><a href="{{ $actionUrl }}" style="display:inline-block;background:#0A5FB5;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:999px">{{ $actionText ?? 'Ouvrir' }}</a></p>
          <p style="margin:6px 0 0;font-size:12px;color:#667085;word-break:break-all">Si le bouton ne fonctionne pas : {{ $actionUrl }}</p>
        @endif
      </td></tr>
      <tr><td style="padding:16px 28px 24px;font-size:12px;color:#667085;border-top:1px solid #E5EAF2">
        {{ $footnote ?? 'Vous recevez cet e-mail car vous utilisez MX-CONNECT. Vous pouvez désactiver ces e-mails dans vos paramètres.' }}<br>
        MX-CONNECT — Ensemble pour une meilleure santé.
      </td></tr>
    </table>
  </td></tr></table>
</body></html>
