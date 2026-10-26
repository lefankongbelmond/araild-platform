<x-layouts.social title="Créer un compte" bare>
  <main class="mx-auth" id="contenu">
    <div class="mx-card mx-card__pad mx-stack">
      <div style="text-align:center"><img src="/brand/mx-logo-full.webp" alt="MX-CONNECT" width="180" height="136" style="margin:0 auto"><h1 style="font-size:22px;margin-top:10px">Rejoindre MX-CONNECT</h1><p class="mx-small mx-muted">Suivez les mutuelles de santé, réagissez, partagez.</p></div>
      <form method="POST" action="{{ route('social.register') }}" class="mx-stack">
        @csrf
        <div class="mx-grid-2">
          <div class="mx-field"><label class="mx-label" for="r-fn">Prénom</label><input class="mx-input" id="r-fn" name="first_name" value="{{ old('first_name') }}" required maxlength="120" autocomplete="given-name">@error('first_name')<p class="mx-error">{{ $message }}</p>@enderror</div>
          <div class="mx-field"><label class="mx-label" for="r-ln">Nom</label><input class="mx-input" id="r-ln" name="last_name" value="{{ old('last_name') }}" required maxlength="120" autocomplete="family-name">@error('last_name')<p class="mx-error">{{ $message }}</p>@enderror</div>
        </div>
        <div class="mx-field"><label class="mx-label" for="r-phone">Téléphone</label><input class="mx-input" id="r-phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel" placeholder="+225 07 00 00 00 00">
          <p class="mx-help">Sert d'identifiant de connexion. Indicatif pays conseillé.</p>@error('phone')<p class="mx-error">{{ $message }}</p>@enderror</div>
        <div class="mx-field"><label class="mx-label" for="r-email">E-mail (facultatif)</label><input class="mx-input" id="r-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email">@error('email')<p class="mx-error">{{ $message }}</p>@enderror</div>
        <div class="mx-grid-2">
          <div class="mx-field"><label class="mx-label" for="r-pw">Mot de passe</label><input class="mx-input" id="r-pw" type="password" name="password" required minlength="8" autocomplete="new-password">@error('password')<p class="mx-error">{{ $message }}</p>@enderror</div>
          <div class="mx-field"><label class="mx-label" for="r-pw2">Confirmation</label><input class="mx-input" id="r-pw2" type="password" name="password_confirmation" required autocomplete="new-password"></div>
        </div>
        <p class="mx-help">8 caractères minimum, avec au moins une lettre et un chiffre.</p>
        <label class="mx-check"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))> <span>J'accepte les règles de la communauté et la <a href="{{ route('social.privacy') }}" target="_blank">politique de confidentialité</a>.</span></label>
        @error('terms')<p class="mx-error">{{ $message }}</p>@enderror
        <button class="mx-btn mx-btn--primary mx-btn--lg mx-btn--block">Créer mon compte</button>
      </form>
      <p class="mx-small" style="text-align:center">Déjà inscrit ? <a href="{{ route('social.login') }}">Se connecter</a></p>
    </div>
  </main>
</x-layouts.social>
