@php $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-sm'; @endphp
<x-layouts.provider title="Votre mot de passe" :bare="true">
  <div class="mx-auto max-w-md rounded-3xl bg-white p-6 shadow-soft ring-1 ring-ink/[.05] sm:p-8">
    <h1 class="text-xl font-bold text-[#0B1F3A]">Choisissez votre mot de passe</h1>
    <p class="mt-1 text-sm text-ink/60">Bonjour {{ $account->first_name }}, votre compte a été créé avec un mot de passe provisoire. Remplacez-le par un mot de passe personnel.</p>
    <form method="POST" action="{{ route('pro.password.update') }}" class="mt-5 space-y-3">@csrf
      <label class="block text-sm font-medium">Mot de passe provisoire<input type="password" name="current_password" required autocomplete="current-password" class="{{ $f }}"></label>
      @error('current_password')<p class="text-xs text-clay">{{ $message }}</p>@enderror
      <label class="block text-sm font-medium">Nouveau mot de passe <span class="font-normal text-ink/50">(10 caractères au moins, lettres et chiffres)</span><input type="password" name="password" required autocomplete="new-password" class="{{ $f }}"></label>
      @error('password')<p class="text-xs text-clay">{{ $message }}</p>@enderror
      <label class="block text-sm font-medium">Confirmer<input type="password" name="password_confirmation" required autocomplete="new-password" class="{{ $f }}"></label>
      <button class="w-full rounded-md bg-pine px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy">Enregistrer et continuer</button>
    </form>
  </div>
</x-layouts.provider>
