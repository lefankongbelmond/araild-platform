<x-layouts.social title="{{ __('Confidentialité') }}" wide>
  <article class="mx-card mx-card__pad mx-stack" style="max-width:760px">
    <h1 style="font-size:26px">{{ __('Politique de confidentialité') }}</h1>
    <p class="mx-small mx-muted">{{ __('Résumé des règles appliquées par la plateforme. Le texte juridique complet est fourni par l\'exploitant du réseau.') }}</p>
    <h2 style="font-size:18px">{{ __('Données collectées') }}</h2>
    <ul style="padding-left:18px">
      <li>{{ __('Compte : nom, prénom, téléphone, e-mail facultatif, mot de passe (stocké sous forme chiffrée irréversible).') }}</li>
      <li>{{ __('Profil facultatif : photo, ville, présentation, centres d\'intérêt, visibilité choisie.') }}</li>
      <li>{{ __('Activité : publications, commentaires, réactions, enregistrements, abonnements, signalements.') }}</li>
      <li>{{ __('Messages de contact et messages privés : visibles uniquement par vous et la mutuelle concernée.') }}</li>
      <li>{{ __('Demandes d’adhésion en ligne : identité, coordonnées, composition du foyer et pièces jointes que vous choisissez d’envoyer. Elles sont transmises uniquement à la mutuelle choisie ; les pièces sont conservées hors de l’espace public du site et ne sont lisibles que par vous et les agents habilités de cette mutuelle.') }}</li>
    </ul>
    <h2 style="font-size:18px">{{ __('Ce que nous ne collectons pas sur le réseau social') }}</h2>
    <p>{{ __('Aucune donnée médicale n\'est demandée. Les dossiers d\'adhérents, cotisations et prestations restent dans l\'espace de gestion sécurisé de chaque mutuelle, séparé du réseau public, et ne sont jamais publiés.') }}</p>
    <h2 style="font-size:18px">{{ __('Vos choix') }}</h2>
    <ul style="padding-left:18px">
      <li>{{ __('Rendre votre profil privé, modifier ou retirer votre photo (Paramètres).') }}</li>
      <li>{{ __('Supprimer vos publications et commentaires à tout moment.') }}</li>
      <li>{{ __('Ne plus suivre ou masquer une mutuelle.') }}</li>
      <li>{{ __('Supprimer vous-même votre compte et vos données du réseau social (Paramètres → Supprimer mon compte).') }}</li>
    </ul>
    <h2 style="font-size:18px">{{ __('Photos') }}</h2>
    <p>{{ __('Les images publiées sont réencodées par le serveur : les métadonnées (position GPS, appareil…) sont supprimées.') }}</p>
  </article>
</x-layouts.social>
