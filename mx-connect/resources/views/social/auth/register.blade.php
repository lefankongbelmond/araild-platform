<x-layouts.social :title="__('mxconnect.social.auth.register_title')" full>
    <div class="mx-auto max-w-lg">
        <div class="card p-6 sm:p-8">
            <h1 class="text-2xl font-extrabold tracking-tight text-navy">{{ __('mxconnect.social.auth.register_title') }}</h1>
            <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.social.auth.register_sub') }}</p>
            <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.auth.first_name') }}
                        <input name="first_name" required maxlength="120" autocomplete="given-name" value="{{ old('first_name') }}" class="field mt-1 font-normal">
                        @error('first_name')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.auth.last_name') }}
                        <input name="last_name" required maxlength="120" autocomplete="family-name" value="{{ old('last_name') }}" class="field mt-1 font-normal">
                        @error('last_name')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
                    </label>
                </div>
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.auth.phone') }}
                    <input name="phone" type="tel" required autocomplete="tel" value="{{ old('phone') }}" placeholder="+237650000000" class="field mt-1 font-normal">
                    <span class="mt-1 block text-xs font-normal text-ink/50">{{ __('mxconnect.social.auth.phone_help') }}</span>
                    @error('phone')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.auth.email') }}
                    <input name="email" type="email" maxlength="180" autocomplete="email" value="{{ old('email') }}" class="field mt-1 font-normal">
                    @error('email')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.auth.password') }}
                        <input name="password" type="password" required autocomplete="new-password" class="field mt-1 font-normal">
                    </label>
                    <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.auth.password_confirmation') }}
                        <input name="password_confirmation" type="password" required autocomplete="new-password" class="field mt-1 font-normal">
                    </label>
                </div>
                @error('password')<span class="block text-xs text-clay">{{ $message }}</span>@enderror
                <label class="flex items-start gap-2 text-sm text-ink/70">
                    <input type="checkbox" name="terms" value="1" required @checked(old('terms')) class="mt-0.5 rounded border-ink/30 text-brand focus:ring-brand">
                    {{ __('mxconnect.social.auth.terms') }}
                </label>
                @error('terms')<span class="block text-xs text-clay">{{ $message }}</span>@enderror
                <button class="btn-primary w-full">{{ __('mxconnect.social.auth.register_title') }}</button>
            </form>
            <p class="mt-6 text-center text-sm text-ink/60">{{ __('mxconnect.social.auth.has_account') }} <a href="{{ route('login') }}" class="font-semibold text-pine hover:underline">{{ __('mxconnect.social.auth.login_title') }}</a></p>
        </div>
    </div>
</x-layouts.social>
