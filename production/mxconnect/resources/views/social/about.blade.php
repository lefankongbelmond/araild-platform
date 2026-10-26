<x-layouts.social title="À propos" wide>
  <article class="mx-card mx-card__pad mx-stack" style="max-width:760px">
    <h1 style="font-size:26px">À propos de MX-CONNECT</h1>
    <p>MX-CONNECT est le réseau social des mutuelles de santé. Il permet aux mutuelles de présenter leurs actions, campagnes de prévention, événements et offres, et au public de les découvrir, de les suivre et d'échanger.</p>
    <h2 style="font-size:18px">Suivre n'est pas adhérer</h2>
    <p>Suivre une mutuelle permet seulement de voir ses publications. L'adhésion (devenir bénéficiaire) est une démarche distincte, réalisée auprès de la mutuelle.</p>
    <h2 style="font-size:18px">Règles de la communauté</h2>
    <ul style="padding-left:18px">
      <li>Pas d'information médicale personnelle (la vôtre ou celle d'autrui) dans les publications et commentaires.</li>
      <li>Respect, bienveillance : pas de propos haineux, de harcèlement ni de spam.</li>
      <li>Pas de fausses informations de santé ; citez des sources fiables.</li>
      <li>Signalez tout contenu problématique avec le bouton « Signaler ».</li>
    </ul>
    <h2 style="font-size:18px">Mutuelles vérifiées</h2>
    <p>Le badge <span class="mx-verified"><x-mx.icon name="verified" :size="16"/></span> signale une organisation vérifiée : son inscription a été contrôlée et validée par l'administration du réseau MX-CONNECT (procédure d’agrément), et non simplement déclarée par l’organisation. Les contenus marqués « Démo » sont fictifs et servent uniquement à la démonstration.</p>
    <p><a class="mx-btn mx-btn--primary" href="{{ route('social.mutuals') }}">Découvrir les mutuelles</a></p>
  </article>
</x-layouts.social>
