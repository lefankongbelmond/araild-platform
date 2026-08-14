<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ __('mxconnect.card.title') }} — {{ $member->member_code }}</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{ink:'#0f2e2b',pine:'#12433d',mist:'#eef3f1',paper:'#f7f5f0'}}}}</script>
</head>
<body class="bg-paper p-6 text-ink">
  <div class="mx-auto max-w-md">
    {{-- Card --}}
    <div class="overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-sm">
      <div class="bg-pine px-5 py-3 text-white flex items-center justify-between">
        <div class="flex items-center gap-2"><span class="h-5 w-5 rounded-sm bg-white/90"></span><span class="font-semibold">MX-CONNECT</span></div>
        <span class="text-xs opacity-80">{{ tenant('name') }}</span>
      </div>
      <div class="p-5 flex gap-4">
        <div class="flex-1">
          <div class="text-lg font-semibold">{{ $member->last_name }} {{ $member->first_name }}</div>
          <div class="mt-1 font-mono text-sm text-ink/60">{{ $member->member_code }}</div>
          <dl class="mt-3 space-y-1 text-sm">
            <div><dt class="inline text-ink/50">{{ __('mxconnect.guarantee.plural') }}:</dt> <dd class="inline">{{ $subscription->guaranteeVersion->guarantee->name }}</dd></div>
            <div><dt class="inline text-ink/50">{{ __('mxconnect.card.valid_from') }}:</dt> <dd class="inline">{{ $subscription->effective_date?->format('d/m/Y') }}</dd></div>
          </dl>
        </div>
        <div class="text-center">
          {{-- QR encodes a signed verification URL — no medical data --}}
          {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(120)->generate(route('members.card.verify', ['t' => $token])) !!}
          <div class="mt-1 text-[10px] text-ink/40">{{ __('mxconnect.card.scan') }}</div>
        </div>
      </div>
    </div>
    <p class="mt-4 text-center text-xs text-ink/40">{{ __('mxconnect.card.no_medical') }}</p>
    <div class="mt-4 text-center"><button onclick="window.print()" class="rounded-md bg-pine px-4 py-2 text-sm text-white">{{ __('mxconnect.card.print') }}</button></div>
  </div>
</body>
</html>
