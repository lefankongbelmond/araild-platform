{{-- Centre d'aide et retours bêta (2026-11-09). Les réponses décrivent les fonctions réellement disponibles. --}}
@php
  $faq = [
    ['Comment publier ?', 'Depuis l’accueil, touchez « Quoi de neuf » ou le bouton + : texte, jusqu’à 6 photos ou une vidéo (100 Mo et 15 minutes au plus). Les photos sont nettoyées de leurs métadonnées (position GPS, appareil).'],
    ['Suivre une organisation, est-ce adhérer ?', 'Non. Suivre une organisation affiche ses publications dans votre fil. L’adhésion à une mutuelle est une démarche distincte, depuis la page de la mutuelle (« Adhérer en ligne » quand elle le propose).'],
    ['Comment m’engager ou soutenir un projet ?', 'Ouvrez un projet puis « Je m’engage » ou « Soutenir ». L’organisation reçoit votre demande et vous répond ; vous suivez son état dans Profil → Mes opportunités. MX-CONNECT ne collecte aucun argent : un soutien financier se convient directement avec l’organisation.'],
    ['Comment écrire à une mutuelle ?', 'Sur la page de la mutuelle, touchez « Message ». Vos échanges se trouvent dans l’onglet Messages ; seuls vous et la mutuelle les voient.'],
    ['J’ai oublié mon mot de passe', 'Sur l’écran de connexion, « Mot de passe oublié ? » : un lien est envoyé à votre adresse e-mail. Un compte sans e-mail ne peut pas encore être récupéré (pas d’envoi de SMS pour l’instant) : ajoutez une adresse e-mail dans Paramètres.'],
    ['Recevrai-je des notifications sur mon téléphone ?', 'Pendant la bêta, les notifications s’affichent dans l’application (cloche) et, si vous l’avez choisi, par e-mail. Les notifications Android « push » arriveront dans une prochaine version.'],
    ['Mes informations de santé sont-elles visibles ?', 'Non. Aucune donnée médicale n’est demandée ni publiée sur le réseau social. N’en publiez pas : c’est une règle de la communauté.'],
    ['Comment signaler un contenu ?', 'Touchez « … » sur une publication, un commentaire, un profil ou une page, puis « Signaler ». La modération du réseau examine chaque signalement.'],
    ['Quelles autorisations utilise l’application ?', 'Uniquement l’accès à Internet. Pour publier une photo, Android vous laisse choisir un fichier ; la caméra n’est utilisée que si vous choisissez « Prendre une photo ». Ni contacts, ni position, ni SMS.'],
  ];
@endphp
<x-layouts.social title="Centre d’aide" active="menu">
  <section class="mx-card mx-card__pad mx-stack">
    <h1 style="font-size:22px">Centre d’aide</h1>
    @foreach ($faq as [$q, $a])
      <details style="border-bottom:1px solid var(--mx-border);padding:10px 0"><summary style="font-weight:600;cursor:pointer;color:var(--mx-heading)">{{ $q }}</summary><p class="mx-small" style="margin:8px 0 0;line-height:1.55">{{ $a }}</p></details>
    @endforeach
    <p class="mx-small"><a href="{{ route('social.legal.show', ['type' => 'community']) }}">Règles communautaires</a> · <a href="{{ route('social.privacy') }}">Confidentialité</a> · <a href="{{ route('social.account.deletion') }}">Supprimer mon compte</a></p>
  </section>

  <section class="mx-card mx-card__pad mx-stack" style="margin-top:12px" id="retour">
    <h2 class="mx-card__title">Votre avis sur la version bêta</h2>
    <p class="mx-small mx-muted">Un problème, une idée ? Votre message est lu par l’équipe MX-CONNECT. N’y indiquez aucune information médicale.</p>
    <form method="POST" action="{{ route('social.feedback.store') }}" class="mx-stack">
      @csrf
      <fieldset style="border:0;padding:0;margin:0"><legend class="mx-label">C’est…</legend>
        <div class="mx-row" style="flex-wrap:wrap;gap:8px">
          @foreach (\App\Models\Social\BetaFeedback::CATEGORIES as $k => $l)
            <label class="mx-chip"><input type="radio" name="category" value="{{ $k }}" @checked(old('category', 'bug') === $k)> {{ $l }}</label>
          @endforeach
        </div>
      </fieldset>
      <div class="mx-field"><label class="mx-label" for="fb-msg">Message</label><textarea class="mx-textarea" id="fb-msg" name="message" maxlength="3000" required>{{ old('message') }}</textarea>@error('message')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="fb-screen">Écran concerné (facultatif)</label><input class="mx-input" id="fb-screen" name="screen" maxlength="120" value="{{ old('screen', request('ecran')) }}" placeholder="Ex. : fil d’actualité, page d’un projet…"></div>
      <div class="mx-field"><label class="mx-label" for="fb-device">Téléphone et version d’Android (facultatif)</label><input class="mx-input" id="fb-device" name="device" maxlength="160" value="{{ old('device') }}" placeholder="Ex. : Tecno Spark 10, Android 13"></div>
      @guest('member')<div class="mx-field"><label class="mx-label" for="fb-contact">Pour vous répondre (facultatif)</label><input class="mx-input" id="fb-contact" name="contact" maxlength="180" value="{{ old('contact') }}" placeholder="E-mail ou téléphone"></div>@endguest
      <button class="mx-btn mx-btn--primary">Envoyer mon retour</button>
    </form>
  </section>
</x-layouts.social>
