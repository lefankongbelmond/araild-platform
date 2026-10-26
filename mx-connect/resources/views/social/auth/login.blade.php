<x-layouts.social :title="__('mxconnect.social.auth.login_title')" full>
    <div class="mx-auto grid max-w-4xl overflow-hidden rounded-3xl bg-white shadow-card ring-1 ring-ink/5 md:grid-cols-2">
        <div class="relative hidden bg-navy-900 p-10 text-white md:block">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(46,158,79,.5),transparent_50%)]"></div>
            <div class="relative">
                <p class="text-3xl font-extrabold leading-tight">{{ __('mxconnect.social.home.title_1') }} <span class="text-[#5FD07F]">{{ __('mxconnect.social.home.title_2') }}</span></p>
                <p class="mt-4 text-sm text-white/70">{{ __('mxconnect.social.home.sub') }}</p>
            </div>
        </div>
        <div class="p-6 sm:p-10">
            <h1 class="text-2xl font-extrabold tracking-tight text-navy">{{ __('mxconnect.social.auth.login_title') }}</h1>
            <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.social.auth.login_sub') }}</p>
            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.auth.phone') }}
                    <input name="phone" type="tel" autocomplete="tel" required autofocus value="{{ old('phone') }}" placeholder="+237…" class="field mt-1 font-normal">
                    @error('phone')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.auth.password') }}
                    <input name="password" type="password" autocomplete="current-password" required class="field mt-1 font-normal">
                </label>
                <label class="flex items-center gap-2 text-sm text-ink/70">
                    <input type="checkbox" name="remember" value="1" class="rounded border-ink/30 text-brand focus:ring-brand">{{ __('mxconnect.auth.remember') }}
                </label>
                <button class="btn-primary w-full">{{ __('mxconnect.social.auth.login_title') }}</button>
            </form>
            <p class="mt-6 text-center text-sm text-ink/60">{{ __('mxconnect.social.auth.no_account') }} <a href="{{ route('register') }}" class="font-semibold text-pine hover:underline">{{ __('mxconnect.social.auth.register_title') }}</a></p>
        </div>
    </div>
</x-layouts.social>
