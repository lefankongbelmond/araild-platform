@php $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-sm'; @endphp
<x-layouts.provider title="Nouveau mot de passe" :bare="true">
  <div class="mx-auto max-w-md rounded-3xl bg-white p-6 shadow-soft ring-1 ring-ink/[.05] sm:p-8">
    <h1 class="text-xl font-bold text-[#0B1F3A]">Choisir un nouveau mot de passe</h1>
    <form method="POST" action="{{ route('pro.reset.update') }}" class="mt-5 space-y-3">@csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <label class="block text-sm font-medium">Adresse e-mail<input type="email" name="email" required value="{{ old('email', $email) }}" class="{{ $f }}"></label>
      @error('email')<p class="text-xs text-clay">{{ $message }}</p>@enderror
      <label class="block text-sm font-medium">Nouveau mot de passe <span class="font-normal text-ink/50">(10 caractères au moins, lettres et chiffres)</span><input type="password" name="password" required autocomplete="new-password" class="{{ $f }}"></label>
      @error('password')<p class="text-xs text-clay">{{ $message }}</p>@enderror
      <label class="block text-sm font-medium">Confirmer<input type="password" name="password_confirmation" required autocomplete="new-password" class="{{ $f }}"></label>
      <button class="w-full rounded-md bg-pine px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy">Enregistrer</button>
    </form>
  </div>
</x-layouts.provider>
