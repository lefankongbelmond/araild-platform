<x-layouts.social title="Connexion" bare>
  <main class="mx-auth" id="contenu">
    <div class="mx-card mx-card__pad mx-stack">
      <div style="text-align:center"><img src="/brand/mx-logo-full.webp" alt="MX-CONNECT" width="180" height="136" style="margin:0 auto"><h1 style="font-size:22px;margin-top:10px">Connexion à MX-CONNECT</h1><p class="mx-small mx-muted">Ensemble pour une meilleure santé.</p></div>
      @if (session('ok'))<div class="mx-alert mx-alert--ok">{{ session('ok') }}</div>@endif
      <form method="POST" action="{{ route('social.login') }}" class="mx-stack">
        @csrf
        <div class="mx-field"><label class="mx-label" for="l-login">Téléphone ou e-mail</label>
          <input class="mx-input" id="l-login" name="login" value="{{ old('login') }}" required autofocus autocomplete="username" placeholder="+225 07 00 00 00 00">
          @error('login')<p class="mx-error">{{ $message }}</p>@enderror</div>
        <div class="mx-field"><label class="mx-label" for="l-pw">Mot de passe</label>
          <input class="mx-input" id="l-pw" type="password" name="password" required autocomplete="current-password"></div>
        <label class="mx-check"><input type="checkbox" name="remember" value="1"> Rester connecté sur cet appareil</label>
        <button class="mx-btn mx-btn--primary mx-btn--lg mx-btn--block">Se connecter</button>
      </form>
      <p class="mx-small" style="text-align:center">Pas encore de compte ? <a href="{{ route('social.register') }}">Créer un compte</a></p>
      <p class="mx-small" style="text-align:center"><a href="{{ route('social.password.request') }}">Mot de passe oublié ?</a></p>
      <p class="mx-xs mx-muted" style="text-align:center">Personnel d'une mutuelle : connectez-vous depuis l'adresse de votre mutuelle.</p>
    </div>
  </main>
</x-layouts.social>
