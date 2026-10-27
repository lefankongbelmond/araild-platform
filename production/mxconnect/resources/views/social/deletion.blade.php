{{-- Page PUBLIQUE de suppression de compte (exigence Google Play pour l'application Android). --}}
<x-layouts.social title="Supprimer mon compte" wide>
  <article class="mx-card mx-card__pad mx-stack" style="max-width:760px">
    <h1 style="font-size:26px">Supprimer votre compte MX-CONNECT</h1>
    <p>Vous pouvez supprimer votre compte à tout moment, depuis le site ou l’application Android, sans avoir à nous écrire.</p>
    <h2 style="font-size:18px">Comment faire</h2>
    <ol style="padding-left:20px;line-height:1.7">
      <li>Connectez-vous (site ou application).</li>
      <li>Ouvrez <b>Paramètres</b> (dans l’application : <b>Profil → Paramètres et confidentialité</b>).</li>
      <li>En bas de la page, section <b>Supprimer mon compte</b> : saisissez votre mot de passe et le mot <b>SUPPRIMER</b>, puis confirmez.</li>
    </ol>
    @auth('member')<a class="mx-btn mx-btn--primary" href="{{ route('social.settings') }}#supprimer">Ouvrir mes paramètres</a>@else<a class="mx-btn mx-btn--primary" href="{{ route('social.login') }}">Se connecter</a>@endauth
    <h2 style="font-size:18px">Ce qui est supprimé</h2>
    <ul style="padding-left:20px;line-height:1.7">
      <li>Votre compte, votre profil, vos photos, vos publications, commentaires, réactions, abonnements et enregistrements.</li>
      <li>Vos demandes (adhésion, engagement, soutien, candidatures) et leurs pièces jointes, vos messages et notifications.</li>
      <li>L’historique de vos acceptations des documents.</li>
    </ul>
    <h2 style="font-size:18px">Ce qui n’est pas supprimé</h2>
    <ul style="padding-left:20px;line-height:1.7">
      <li>Si vous êtes adhérent d’une mutuelle, votre dossier d’adhérent (cotisations, prestations) reste dans l’espace de gestion de cette mutuelle, qui en est responsable : adressez-vous à elle.</li>
      <li>Les journaux techniques du serveur (sécurité), pendant leur durée de conservation technique.</li>
    </ul>
    <p class="mx-small mx-muted">Mot de passe oublié ? <a href="{{ route('social.password.request') }}">Réinitialisez-le</a> (compte avec adresse e-mail).</p>
  </article>
</x-layouts.social>
