<x-layouts.social title="Nouveau mot de passe" bare>
  <main class="mx-auth" id="contenu">
    <div class="mx-card mx-card__pad mx-stack">
      <div style="text-align:center"><img src="/brand/mx-logo-full.webp" alt="MX-CONNECT" width="150" height="113" style="margin:0 auto"><h1 style="font-size:20px;margin-top:10px">Choisir un nouveau mot de passe</h1></div>
      <form method="POST" action="{{ route('social.password.update') }}" class="mx-stack">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="mx-field"><label class="mx-label" for="r-email">Adresse e-mail</label><input class="mx-input" id="r-email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email">@error('email')<p class="mx-error">{{ $message }}</p>@enderror</div>
        <div class="mx-field"><label class="mx-label" for="r-pw">Nouveau mot de passe</label><input class="mx-input" id="r-pw" type="password" name="password" required minlength="8" autocomplete="new-password">@error('password')<p class="mx-error">{{ $message }}</p>@enderror</div>
        <div class="mx-field"><label class="mx-label" for="r-pw2">Confirmation</label><input class="mx-input" id="r-pw2" type="password" name="password_confirmation" required autocomplete="new-password"></div>
        <p class="mx-help">8 caractères minimum, avec au moins une lettre et un chiffre.</p>
        <button class="mx-btn mx-btn--primary mx-btn--block">Enregistrer</button>
      </form>
    </div>
  </main>
</x-layouts.social>
