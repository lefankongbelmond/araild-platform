{{-- Page PUBLIQUE de suppression de compte (exigence Google Play pour l'application Android). --}}
<x-layouts.social title="{{ __('Supprimer mon compte') }}" wide>
  <article class="mx-card mx-card__pad mx-stack" style="max-width:760px">
    <h1 style="font-size:26px">{{ __('Supprimer votre compte MX-CONNECT') }}</h1>
    <p>{{ __('Vous pouvez supprimer votre compte à tout moment, depuis le site ou l’application Android, sans avoir à nous écrire.') }}</p>
    <h2 style="font-size:18px">{{ __('Comment faire') }}</h2>
    <ol style="padding-left:20px;line-height:1.7">
      <li>{{ __('Connectez-vous (site ou application).') }}</li>
      <li>{{ __('Ouvrez') }} <b>{{ __('Paramètres') }}</b> {{ __('(dans l’application :') }} <b>{{ __('Profil → Paramètres et confidentialité') }}</b>).</li>
      <li>{{ __('En bas de la page, section') }} <b>{{ __('Supprimer mon compte') }}</b> {{ __(': saisissez votre mot de passe et le mot') }} <b>{{ __('SUPPRIMER') }}</b>{{ __(', puis confirmez.') }}</li>
    </ol>
    @auth('member')<a class="mx-btn mx-btn--primary" href="{{ route('social.settings') }}#supprimer">{{ __('Ouvrir mes paramètres') }}</a>@else<a class="mx-btn mx-btn--primary" href="{{ route('social.login') }}">{{ __('Se connecter') }}</a>@endauth
    <h2 style="font-size:18px">{{ __('Ce qui est supprimé') }}</h2>
    <ul style="padding-left:20px;line-height:1.7">
      <li>{{ __('Votre compte, votre profil, vos photos, vos publications, commentaires, réactions, abonnements et enregistrements.') }}</li>
      <li>{{ __('Vos demandes (adhésion, engagement, soutien, candidatures) et leurs pièces jointes, vos messages et notifications.') }}</li>
      <li>{{ __('L’historique de vos acceptations des documents.') }}</li>
    </ul>
    <h2 style="font-size:18px">{{ __('Ce qui n’est pas supprimé') }}</h2>
    <ul style="padding-left:20px;line-height:1.7">
      <li>{{ __('Si vous êtes adhérent d’une mutuelle, votre dossier d’adhérent (cotisations, prestations) reste dans l’espace de gestion de cette mutuelle, qui en est responsable : adressez-vous à elle.') }}</li>
      <li>{{ __('Les journaux techniques du serveur (sécurité), pendant leur durée de conservation technique.') }}</li>
    </ul>
    <p class="mx-small mx-muted">{{ __('Mot de passe oublié ?') }} <a href="{{ route('social.password.request') }}">{{ __('Réinitialisez-le') }}</a> {{ __('(compte avec adresse e-mail).') }}</p>
  </article>
</x-layouts.social>
