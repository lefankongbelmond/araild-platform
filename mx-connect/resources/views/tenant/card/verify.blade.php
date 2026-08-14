<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ __('mxconnect.card.verify_title') }}</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{ink:'#0f2e2b',pine:'#12433d',clay:'#c86f4a',paper:'#f7f5f0'}}}}</script>
</head>
<body class="bg-paper p-6 text-ink">
  <div class="mx-auto max-w-sm text-center">
    @if ($ok)
      <div class="rounded-2xl border border-pine/30 bg-pine/5 p-8">
        <div class="text-5xl">✓</div>
        <div class="mt-3 text-xl font-semibold text-pine">{{ __('mxconnect.card.rights_ok') }}</div>
        @if ($member)<div class="mt-2 text-sm text-ink/70">{{ $member->last_name }} {{ $member->first_name }} · {{ $member->member_code }}</div>@endif
      </div>
    @else
      <div class="rounded-2xl border border-clay/30 bg-clay/5 p-8">
        <div class="text-5xl">✕</div>
        <div class="mt-3 text-xl font-semibold text-clay">{{ __('mxconnect.card.rights_ko') }}</div>
        <ul class="mt-3 text-sm text-ink/70">
          @foreach ($reasons as $r)<li>{{ __('mxconnect.rights.'.$r) }}</li>@endforeach
        </ul>
      </div>
    @endif
    <p class="mt-4 text-xs text-ink/40">{{ __('mxconnect.card.no_medical') }}</p>
  </div>
</body>
</html>
