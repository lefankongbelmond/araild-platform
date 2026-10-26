<x-layouts.network :title="__('mxconnect.auth.login')">
  <div class="mx-auto max-w-sm mt-10">
    <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.auth.login') }}</h1>
    <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.auth.login_sub') }}</p>

    <form method="POST" action="{{ route('network.login') }}" class="mt-6 space-y-4">
      @csrf
      <div>
        <label class="block text-sm font-medium">{{ __('mxconnect.auth.email') }}</label>
        <input name="email" type="email" value="{{ old('email') }}" required autofocus
               class="mt-1 w-full rounded-md border-ink/20 bg-white px-3 py-2 text-sm focus:border-pine focus:ring-pine">
        @error('email')<p class="mt-1 text-xs text-clay">{{ $message }}</p>@enderror
      </div>
      <div>
        <label class="block text-sm font-medium">{{ __('mxconnect.auth.password') }}</label>
        <input name="password" type="password" required
               class="mt-1 w-full rounded-md border-ink/20 bg-white px-3 py-2 text-sm focus:border-pine focus:ring-pine">
      </div>
      <label class="flex items-center gap-2 text-sm text-ink/70">
        <input type="checkbox" name="remember" class="rounded border-ink/30 text-pine focus:ring-pine">
        {{ __('mxconnect.auth.remember') }}
      </label>
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">
        {{ __('mxconnect.auth.login') }}
      </button>
    </form>
  </div>
</x-layouts.network>
