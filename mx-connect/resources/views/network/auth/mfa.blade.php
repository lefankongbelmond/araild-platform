<x-layouts.network :title="__('mxconnect.mfa.title')">
  <div class="mx-auto max-w-sm mt-10">
    <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.mfa.title') }}</h1>

    @if ($mode === 'enroll')
      <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.mfa.enroll_help') }}</p>
      <div class="mt-4 rounded-lg border border-ink/10 bg-white p-4">
        {{-- Render the otpauth:// URL as a QR via F9WebLtd\QrCode --}}
        {!! \F9WebLtd\QrCode\Facades\QrCode::size(180)->generate($qrUrl) !!}
      </div>
      <form method="POST" action="{{ route('mfa.confirm') }}" class="mt-4 space-y-3">
    @else
      <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.mfa.challenge_help') }}</p>
      <form method="POST" action="{{ route('mfa.verify') }}" class="mt-6 space-y-3">
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
</x-layouts.network>
