<x-layouts.tenant :title="__('mxconnect.mfa.title')">
  <div class="mx-auto max-w-sm mt-10">
    <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.mfa.title') }}</h1>

    @if ($mode === 'enroll')
      <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.mfa.enroll_help') }}</p>
      <div class="mt-4 rounded-lg border border-ink/10 bg-white p-4">
        {{-- QR code de l'URL otpauth:// ; en cas d'échec (extension PHP absente), la clé reste saisissable à la main. --}}
        @php
          try { $qrSvg = (string) \F9WebLtd\QrCode\Facades\QrCode::size(180)->generate($qrUrl); }
          catch (\Throwable $e) { report($e); $qrSvg = null; }
          parse_str((string) parse_url($qrUrl, PHP_URL_QUERY), $otp);
          $otpKey = trim(chunk_split((string) ($otp['secret'] ?? ''), 4, ' '));
        @endphp
        @if ($qrSvg){!! $qrSvg !!}@else<p class="text-sm text-clay">Le QR code ne peut pas être affiché sur ce serveur : saisissez la clé ci-dessous dans votre application.</p>@endif
        @if ($otpKey)<p class="mt-3 text-xs text-ink/60">Ou saisissez cette clé dans Google Authenticator / Microsoft Authenticator (« Saisir une clé ») :</p>
          <p class="mt-1 select-all break-all rounded bg-mist px-2 py-1 font-mono text-sm tracking-wider">{{ $otpKey }}</p>@endif
      </div>
      <form method="POST" action="{{ route('tenant.mfa.confirm') }}" class="mt-4 space-y-3">
    @else
      <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.mfa.challenge_help') }}</p>
      <form method="POST" action="{{ route('tenant.mfa.verify') }}" class="mt-6 space-y-3">
    @endif
      @csrf
      <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus
             placeholder="000000"
             class="w-full rounded-md border-ink/20 bg-white px-3 py-2 text-center tracking-[0.4em] text-lg focus:border-pine focus:ring-pine">
      @error('code')<p class="text-xs text-clay">{{ $message }}</p>@enderror
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">
        {{ __('mxconnect.mfa.verify') }}
      </button>
    </form>
  </div>
</x-layouts.tenant>
