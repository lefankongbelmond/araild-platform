{{ $heading }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
@if ($actionUrl)
{{ $actionText ?? __('Ouvrir') }} : {{ $actionUrl }}

@endif
--
{{ $footnote ?? __('Vous recevez cet e-mail car vous utilisez MX-CONNECT. Vous pouvez désactiver ces e-mails dans vos paramètres.') }}
{{ __('MX-CONNECT — Ensemble pour une meilleure santé.') }}
