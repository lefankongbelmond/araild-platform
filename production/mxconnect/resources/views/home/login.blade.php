<!DOCTYPE html>
<html lang="fr">
<head>
  @include('home.partials.head')
  <title>Se connecter · MX-CONNECT</title>
</head>
<body class="h-body">
  @include('home.partials.header', ['current' => 'login'])
  <main class="h-section h-section--alt" id="contenu" style="min-height:70vh">
    <div class="h-wrap">
      <div class="h-center"><span class="h-eyebrow">Se connecter</span><h1 class="h-title">Choisissez votre espace</h1>
        <p class="h-sub">Chaque profil dispose de son propre espace sécurisé.</p></div>
      <div class="h-login-grid">
        <div class="h-login-card">
          <span class="h-mod__ico h-mod__ico--g"><x-mx.icon name="users" :size="22"/></span>
          <h2>Membre / grand public</h2>
          <p>Accédez au réseau social, suivez les mutuelles, vos demandes d’adhésion et vos messages.</p>
          <a class="h-btn h-btn--grad" href="{{ route('social.login') }}">Connexion membre</a>
          <a href="{{ route('social.register') }}" style="text-align:center;font-size:14px">Pas encore de compte ? Inscription</a>
        </div>
        <div class="h-login-card">
          <span class="h-mod__ico h-mod__ico--b"><x-mx.icon name="building" :size="22"/></span>
          <h2>Personnel d’une mutuelle</h2>
          <p>Saisissez l’identifiant de votre mutuelle (le début de son adresse, ex. <em>mutueltest</em> pour <em>mutueltest.{{ $base }}</em>).</p>
          <form method="POST" action="{{ route('home.login.mutual') }}">
            @csrf
            <label class="mx-sr" for="l-mut">Identifiant de la mutuelle</label>
            <input id="l-mut" name="mutuelle" value="{{ old('mutuelle') }}" required maxlength="120" placeholder="ex. mutueltest" autocomplete="off">
            @error('mutuelle')<p class="h-err" style="color:var(--mx-danger);font-size:13px">{{ $message }}</p>@enderror
            <button class="h-btn h-btn--grad">Aller à mon espace</button>
          </form>
        </div>
        <div class="h-login-card">
          <span class="h-mod__ico h-mod__ico--b"><x-mx.icon name="shield" :size="22"/></span>
          <h2>Administration du réseau</h2>
          <p>Réservé à l’équipe MX-CONNECT et aux administrateurs du réseau (double authentification).</p>
          <a class="h-btn h-btn--ghost" href="{{ route('network.login') }}">Administration</a>
        </div>
      </div>
    </div>
  </main>
  @include('home.partials.footer')
</body>
</html>
