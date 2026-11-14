{{-- Mot de passe provisoire d'un compte de l'espace prestataire : affiché UNE seule fois. --}}
@php $tp = session('temp_password'); @endphp
<div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm" role="status">
  <b>Accès à transmettre au responsable de l’établissement</b> (affiché une seule fois) :
  <div class="mt-2 grid gap-1 sm:grid-cols-3">
    <span>Adresse : <b class="select-all">{{ $tp['url'] ?? rtrim((string) config('app.url'), '/') . '/espace-prestataire' }}</b></span>
    <span>Identifiant : <b class="select-all">{{ $tp['email'] }}</b></span>
    <span>Mot de passe provisoire : <b class="select-all font-mono">{{ $tp['password'] }}</b></span>
  </div>
  <p class="mt-2 text-xs text-ink/60">À la première connexion, la personne choisit son mot de passe puis active la double authentification.</p>
</div>
