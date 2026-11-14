<x-layouts.provider title="Double authentification" :bare="true">
  <div class="mx-auto max-w-md rounded-3xl bg-white p-6 shadow-soft ring-1 ring-ink/[.05] sm:p-8">
    <h1 class="text-xl font-bold text-[#0B1F3A]">Double authentification</h1>
    @if ($mode === 'enroll')
      <p class="mt-1 text-sm text-ink/60">Obligatoire pour tout le personnel des établissements : installez Google Authenticator ou Microsoft Authenticator sur votre téléphone, scannez ce code, puis saisissez le code à 6 chiffres affiché.</p>
      @php
        try { $qrSvg = (string) \F9WebLtd\QrCode\Facades\QrCode::size(180)->generate($qrUrl); }
        catch (\Throwable $e) { report($e); $qrSvg = null; }
        parse_str((string) parse_url($qrUrl, PHP_URL_QUERY), $otp);
        $otpKey = trim(chunk_split((string) ($otp['secret'] ?? ''), 4, ' '));
      @endphp
      <div class="mt-4 rounded-lg border border-ink/10 bg-white p-4 text-center">
        @if ($qrSvg)<div class="inline-block">{!! $qrSvg !!}</div>@else<p class="text-sm text-clay">Le QR code ne peut pas être affiché sur ce serveur : saisissez la clé ci-dessous dans votre application.</p>@endif
        @if ($otpKey)<p class="mt-3 text-xs text-ink/60">Ou saisissez cette clé (« Saisir une clé ») :</p><p class="mt-1 select-all break-all rounded bg-mist px-2 py-1 font-mono text-sm tracking-wider">{{ $otpKey }}</p>@endif
      </div>
      <form method="POST" action="{{ route('pro.mfa.confirm') }}" class="mt-4 space-y-3">
    @else
      <p class="mt-1 text-sm text-ink/60">Saisissez le code à 6 chiffres affiché par votre application d’authentification.</p>
      <form method="POST" action="{{ route('pro.mfa.verify') }}" class="mt-5 space-y-3">
    @endif
      @csrf
      <label for="code" class="sr-only">Code à 6 chiffres</label>
      <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus placeholder="000000"
             class="w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-center text-lg tracking-[0.4em] focus:border-pine focus:outline-none">
      @error('code')<p class="text-xs text-clay">{{ $message }}</p>@enderror
      <button class="w-full rounded-md bg-pine px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy">Valider</button>
    </form>
    @if ($mode === 'challenge')<p class="mt-4 text-xs text-ink/55">Téléphone perdu ou changé ? L’administrateur de votre établissement peut réinitialiser votre double authentification (Personnel et utilisateurs).</p>@endif
  </div>
</x-layouts.provider>
