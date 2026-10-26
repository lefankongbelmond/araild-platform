<x-layouts.social title="Mot de passe oublié" bare>
  <main class="mx-auth" id="contenu">
    <div class="mx-card mx-card__pad mx-stack">
      <div style="text-align:center"><img src="/brand/mx-logo-full.webp" alt="MX-CONNECT" width="150" height="113" style="margin:0 auto"><h1 style="font-size:20px;margin-top:10px">Mot de passe oublié</h1></div>
      @if (session('ok'))<div class="mx-alert mx-alert--ok">{{ session('ok') }}</div>@endif
      @if ($mailReady)
        <p class="mx-small mx-muted">Indiquez l’adresse e-mail de votre compte : vous recevrez un lien pour choisir un nouveau mot de passe (valable 60 minutes).</p>
        <form method="POST" action="{{ route('social.password.email') }}" class="mx-stack">
          @csrf
          <div class="mx-field"><label class="mx-label" for="f-email">Adresse e-mail</label><input class="mx-input" id="f-email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">@error('email')<p class="mx-error">{{ $message }}</p>@enderror</div>
          <button class="mx-btn mx-btn--primary mx-btn--block">Recevoir le lien</button>
        </form>
        <p class="mx-xs mx-muted">Votre compte n’a pas d’adresse e-mail ? Contactez votre mutuelle ou le support MX-CONNECT.</p>
      @else
        <div class="mx-alert mx-alert--info">La réinitialisation par e-mail n’est pas encore activée sur ce site. Contactez votre mutuelle ou le support MX-CONNECT.</div>
      @endif
      <p class="mx-small" style="text-align:center"><a href="{{ route('social.login') }}">Retour à la connexion</a></p>
    </div>
  </main>
</x-layouts.social>
