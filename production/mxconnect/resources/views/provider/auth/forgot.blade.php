<x-layouts.provider title="Mot de passe oublié" :bare="true">
  <div class="mx-auto max-w-md rounded-3xl bg-white p-6 shadow-soft ring-1 ring-ink/[.05] sm:p-8">
    <h1 class="text-xl font-bold text-[#0B1F3A]">Mot de passe oublié</h1>
    @if ($mailReady)
      <p class="mt-1 text-sm text-ink/60">Indiquez l’adresse e-mail de votre compte : vous recevrez un lien valable 60 minutes.</p>
      <form method="POST" action="{{ route('pro.forgot.send') }}" class="mt-5 space-y-3">@csrf
        <label class="block text-sm font-medium">Adresse e-mail<input type="email" name="email" required value="{{ old('email') }}" class="mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-sm"></label>
        @error('email')<p class="text-xs text-clay">{{ $message }}</p>@enderror
        <button class="w-full rounded-md bg-pine px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy">Recevoir le lien</button>
      </form>
    @else
      <p class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm">L’envoi d’e-mails n’est pas encore configuré sur ce site. Demandez à l’administrateur de votre établissement de réinitialiser votre mot de passe depuis « Personnel et utilisateurs ».</p>
    @endif
    <p class="mt-4 text-sm"><a href="{{ route('pro.login') }}" class="text-brand-700 hover:underline">← Retour à la connexion</a></p>
  </div>
</x-layouts.provider>
