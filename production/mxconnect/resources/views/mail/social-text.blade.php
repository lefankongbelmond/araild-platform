{{ $heading }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
@if ($actionUrl)
{{ $actionText ?? 'Ouvrir' }} : {{ $actionUrl }}

@endif
--
{{ $footnote ?? 'Vous recevez cet e-mail car vous utilisez MX-CONNECT. Vous pouvez désactiver ces e-mails dans vos paramètres.' }}
MX-CONNECT — Ensemble pour une meilleure santé.
