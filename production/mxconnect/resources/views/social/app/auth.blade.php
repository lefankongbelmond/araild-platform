{{-- Maquettes 3 (connexion) et 4 (création de compte), branchées sur les vrais formulaires du réseau.
     Écarts assumés : pas de « Continuer avec Google » (la connexion Google n'existe pas) ;
     cases d'accord jamais pré-cochées ; prénom et nom en deux champs (aucune donnée tronquée). --}}
@php
  $codes = ['+237' => 'CM +237', '+225' => 'CI +225', '+221' => 'SN +221', '+229' => 'BJ +229', '+228' => 'TG +228', '+226' => 'BF +226', '+223' => 'ML +223',
            '+227' => 'NE +227', '+241' => 'GA +241', '+242' => 'CG +242', '+243' => 'CD +243', '+235' => 'TD +235', '+236' => 'CF +236', '+224' => 'GN +224', '+33' => 'FR +33'];
  $docIcons = ['terms' => 'document', 'privacy' => 'shield', 'community' => 'users'];
@endphp
<x-layouts.app-plain :title="$tab === 'register' ? 'Créer un compte' : 'Connexion'">
  @if ($tab === 'login')
    <div class="mx-as__top"><span></span></div>
    <h1 class="mx-as__title">Bienvenue sur<br>MX-CONNECT</h1>
    <p class="mx-as__lead">Connectez-vous pour accéder à votre réseau, vos opportunités et vos communautés.</p>
    <nav class="mx-atabs" aria-label="Connexion ou inscription">
      <a href="{{ route('social.app.auth') }}" class="is-active" aria-current="page">Se connecter</a>
      <a href="{{ route('social.app.auth', ['onglet' => 'inscription']) }}">Créer un compte</a>
    </nav>
    @foreach (['ok' => '--ok', 'info' => '--info', 'error' => ''] as $k => $cls)
      @if (session($k))<div class="mx-aalert mx-aalert{{ $cls }}" role="status">{{ session($k) }}</div>@endif
    @endforeach
    <form method="POST" action="{{ route('social.login.store') }}" novalidate>
      @csrf <input type="hidden" name="app" value="1">
      <div class="mx-afield">
        <label for="l-login" id="l-login-label">Email ou identifiant</label>
        <div class="mx-ainput"><span id="l-ic"><x-mx.icon name="mail" :size="20"/></span>
          <input id="l-login" name="login" value="{{ old('login') }}" autocomplete="username" placeholder="votre@email.com ou +237…" required></div>
        @error('login')<p class="mx-aerror">{{ $message }}</p>@enderror
      </div>
      <div class="mx-afield">
        <label for="l-pw">Mot de passe</label>
        <div class="mx-ainput"><x-mx.icon name="lock" :size="20"/>
          <input id="l-pw" type="password" name="password" autocomplete="current-password" required>
          <button type="button" class="mx-ainput__eye" data-eye="l-pw" aria-label="Afficher le mot de passe"><x-mx.icon name="eye" :size="20"/></button></div>
      </div>
      <div class="mx-arowlink">
        {{-- Préférence de confort (pas un accord) : la session reste ouverte dans l'application. --}}
        <label class="mx-acheck" style="margin:0"><input type="checkbox" name="remember" value="1" checked> Se souvenir de moi</label>
        <a href="{{ route('social.password.request') }}">Mot de passe oublié ?</a>
      </div>
      <button class="mx-abtn">Se connecter <x-mx.icon name="arrow-right" :size="18"/></button>
    </form>
    <div class="mx-aor">ou</div>
    <button type="button" class="mx-abtn mx-abtn--ghost" id="l-phone"><x-mx.icon name="phone" :size="18"/> <span>Continuer avec téléphone</span></button>
    <p class="mx-small mx-muted mx-as__center" style="margin-top:18px">Personnel d’une mutuelle ? Connectez-vous depuis l’adresse de votre mutuelle.</p>
  @else
    <div class="mx-as__top"><a class="mx-as__back" href="{{ route('social.app.auth') }}"><x-mx.icon name="arrow-left" :size="22"/> Créer un compte</a></div>
    <p class="mx-as__lead" style="margin-top:10px">Rejoignez MX-CONNECT pour collaborer, partager et agir pour des communautés plus fortes.</p>
    @if ($errors->any())<div class="mx-aalert" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('social.register.store') }}" novalidate>
      @csrf <input type="hidden" name="app" value="1">
      <span class="mx-alabel">Nom et prénom</span>
      <div class="mx-arow2">
        <div class="mx-afield"><div class="mx-ainput"><x-mx.icon name="user" :size="20"/><input name="last_name" value="{{ old('last_name') }}" placeholder="Nom" aria-label="Nom" autocomplete="family-name" required></div></div>
        <div class="mx-afield"><div class="mx-ainput"><input name="first_name" value="{{ old('first_name') }}" placeholder="Prénom" aria-label="Prénom" autocomplete="given-name" required></div></div>
      </div>
      <div class="mx-afield">
        <label for="r-email">Email</label>
        <div class="mx-ainput"><x-mx.icon name="mail" :size="20"/><input id="r-email" type="email" name="email" value="{{ old('email') }}" placeholder="votre@email.com" autocomplete="email"></div>
        <p class="mx-small mx-muted" style="margin:6px 0 0">Il permet de récupérer votre compte en cas d’oubli du mot de passe.</p>
      </div>
      <div class="mx-afield">
        <label for="r-phone">Téléphone (optionnel)</label>
        <div class="mx-ainput">
          <select class="mx-cc" name="phone_cc" aria-label="Indicatif">@foreach ($codes as $v => $l)<option value="{{ $v }}" @selected(old('phone_cc', '+237') === $v)>{{ $l }}</option>@endforeach</select>
          <input id="r-phone" name="phone_local" value="{{ old('phone_local') }}" inputmode="tel" placeholder="6 12 34 56 78" autocomplete="tel-national">
        </div>
      </div>
      <div class="mx-afield">
        <label for="r-pw">Mot de passe</label>
        <div class="mx-ainput"><x-mx.icon name="lock" :size="20"/><input id="r-pw" type="password" name="password" autocomplete="new-password" required>
          <button type="button" class="mx-ainput__eye" data-eye="r-pw" aria-label="Afficher le mot de passe"><x-mx.icon name="eye" :size="20"/></button></div>
        <p class="mx-small mx-muted" style="margin:6px 0 0">8 caractères minimum, avec au moins une lettre et un chiffre.</p>
      </div>
      @forelse ($documents as $d)
        <label class="mx-acheck"><input type="checkbox" name="doc_{{ $d->id }}" value="1" @checked(old('doc_' . $d->id)) required>
          <span>{{ \Illuminate\Support\Str::before($d->checkboxText(), $d->label()) }}<a href="{{ $d->url() }}" target="_blank" rel="noopener">{{ $d->label() }}</a></span></label>
      @empty
        <p class="mx-small mx-muted">Les documents à accepter vous seront présentés à l’étape suivante.</p>
      @endforelse
      <button class="mx-abtn" style="margin-top:14px">Créer mon compte</button>
    </form>
    <p class="mx-small mx-as__center" style="margin-top:16px">Déjà inscrit ? <a href="{{ route('social.app.auth') }}" style="color:var(--mx-sky);font-weight:600">Se connecter</a></p>
  @endif
  <x-slot:scripts><script>
    document.querySelectorAll('[data-eye]').forEach(function (b) { b.addEventListener('click', function () {
      var i = document.getElementById(b.dataset.eye); var show = i.type === 'password'; i.type = show ? 'text' : 'password';
      b.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe'); }); });
    var ph = document.getElementById('l-phone');
    if (ph) ph.addEventListener('click', function () {
      var i = document.getElementById('l-login'), l = document.getElementById('l-login-label'), tel = i.inputMode !== 'tel';
      i.inputMode = tel ? 'tel' : 'email'; i.type = tel ? 'tel' : 'text'; i.placeholder = tel ? '+237 6 12 34 56 78' : 'votre@email.com ou +237…';
      l.textContent = tel ? 'Numéro de téléphone' : 'Email ou identifiant'; ph.querySelector('span').textContent = tel ? 'Continuer avec l’e-mail' : 'Continuer avec téléphone'; i.focus();
    });
  </script></x-slot:scripts>
</x-layouts.app-plain>
