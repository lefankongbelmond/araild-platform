@php $field = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm focus:border-pine focus:outline-none'; @endphp
<x-layouts.tenant title="Mon mot de passe">
  <div class="mx-auto mt-6 max-w-md">
    <h1 class="text-2xl font-semibold tracking-tight">Mon mot de passe</h1>
    @if ($forced)<p class="mt-2 rounded-md border border-ink/10 bg-amber-50 px-3 py-2 text-sm">Vous utilisez un mot de passe provisoire : choisissez votre mot de passe personnel pour continuer.</p>@endif
    @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('account.password.update') }}" class="mt-6 space-y-4 rounded-lg border border-ink/10 bg-white p-5">
      @csrf
      <label class="block text-sm font-medium">Mot de passe actuel{{ $forced ? ' (provisoire)' : '' }}<input type="password" name="current_password" required autocomplete="current-password" class="{{ $field }}"></label>
      <label class="block text-sm font-medium">Nouveau mot de passe<input type="password" name="password" required minlength="10" autocomplete="new-password" class="{{ $field }}"></label>
      <label class="block text-sm font-medium">Confirmer le nouveau mot de passe<input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password" class="{{ $field }}"></label>
      <p class="text-xs text-ink/45">10 caractères minimum, avec des lettres et des chiffres. Ne le communiquez à personne.</p>
      <button class="w-full rounded-md bg-pine px-5 py-2 text-sm font-medium text-white hover:bg-navy">Enregistrer</button>
    </form>
  </div>
</x-layouts.tenant>
