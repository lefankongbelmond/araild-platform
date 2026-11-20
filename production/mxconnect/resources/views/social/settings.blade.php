{{--
  Paramètres du compte membre — refonte M2 (2026-11-28) : la même page, organisée en sections
  (Profil, Compte, Confidentialité, Notifications, Sécurité, Langue, Applications, Mes données).
  Mêmes champs et mêmes routes qu'avant : rien ne change côté enregistrement.
--}}
@php
  $sections = ['profil' => __('Profil'), 'compte' => __('Compte'), 'confidentialite' => __('Confidentialité'), 'notifications' => __('Notifications'),
    'securite' => __('Sécurité'), 'langue' => __('Langue'), 'applications' => __('Applications'), 'donnees' => __('Mes données')];
  $anchor = 'scroll-margin-top:84px';
@endphp
<x-layouts.social :title="__('Paramètres')" active="parametres">
  <h1 style="font-size:22px">{{ __('Paramètres du compte') }}</h1>
  <nav class="mx-chips" aria-label="{{ __('Sections des paramètres') }}" style="margin:6px 0 14px">
    @foreach ($sections as $id => $label)<a class="mx-chip" href="#{{ $id }}">{{ $label }}</a>@endforeach
  </nav>

  {{-- Profil, compte, confidentialité et notifications : un seul formulaire (mêmes règles qu'avant). --}}
  <form class="mx-stack" method="POST" action="{{ route('social.settings.update') }}" enctype="multipart/form-data">
    @csrf
    <section class="mx-card mx-card__pad mx-stack" id="profil" style="{{ $anchor }}">
      <h2 class="mx-card__title">{{ __('Profil') }}</h2>
      <div class="mx-row" style="gap:14px">
        <x-mx.avatar :src="$account->avatarUrl()" :name="$account->name" size="lg" />
        <div class="mx-stack" style="gap:6px">
          <label class="mx-label" for="st-avatar">{{ __('Photo de profil') }}</label>
          <input id="st-avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">
          @if ($account->avatar_path)<label class="mx-check"><input type="checkbox" name="remove_avatar" value="1"> {{ __('Retirer la photo') }}</label>@endif
          @error('avatar')<p class="mx-error">{{ $message }}</p>@enderror
        </div>
      </div>
      <div class="mx-stack" style="gap:6px">
        <label class="mx-label" for="st-cover">{{ __('Photo de couverture') }}</label>
        @if ($account->cover_path)<div class="mx-cover has-img" style="height:110px;border-radius:12px;background-image:url('{{ $account->coverUrl() }}')"></div>@endif
        <input id="st-cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif">
        <p class="mx-help">{{ __('Format paysage conseillé (environ 3 × 1). Convertie automatiquement en WebP.') }}</p>
        @if ($account->cover_path)<label class="mx-check"><input type="checkbox" name="remove_cover" value="1"> {{ __('Retirer la photo de couverture') }}</label>@endif
        @error('cover')<p class="mx-error">{{ $message }}</p>@enderror
      </div>
      <div class="mx-grid-2">
        <div class="mx-field"><label class="mx-label" for="st-fn">{{ __('Prénom') }}</label><input class="mx-input" id="st-fn" name="first_name" value="{{ old('first_name', $account->first_name) }}" required maxlength="120">@error('first_name')<p class="mx-error">{{ $message }}</p>@enderror</div>
        <div class="mx-field"><label class="mx-label" for="st-ln">{{ __('Nom') }}</label><input class="mx-input" id="st-ln" name="last_name" value="{{ old('last_name', $account->last_name) }}" required maxlength="120">@error('last_name')<p class="mx-error">{{ $message }}</p>@enderror</div>
        <div class="mx-field"><label class="mx-label" for="st-city">{{ __('Ville') }}</label><input class="mx-input" id="st-city" name="city" value="{{ old('city', $account->city) }}" maxlength="120"></div>
      </div>
      <div class="mx-field"><label class="mx-label" for="st-bio">{{ __('Présentation') }}</label><textarea class="mx-textarea" id="st-bio" name="bio" maxlength="280" style="min-height:70px">{{ old('bio', $account->bio) }}</textarea><p class="mx-help">{{ __('280 caractères maximum. N\'indiquez pas d\'information sur votre santé.') }}</p></div>
      <fieldset class="mx-field" style="border:0;padding:0">
        <legend class="mx-label">{{ __('Centres d\'intérêt') }}</legend>
        <div class="mx-grid-2" style="gap:6px">
          @foreach ($interests as $k => $v)
            <label class="mx-check"><input type="checkbox" name="interests[]" value="{{ $k }}" @checked(in_array($k, old('interests', $account->interests ?? []), true))> {{ __($v) }}</label>
          @endforeach
        </div>
      </fieldset>
      <div><button class="mx-btn mx-btn--primary">{{ __('Enregistrer') }}</button></div>
    </section>

    <section class="mx-card mx-card__pad mx-stack" id="compte" style="{{ $anchor }}">
      <h2 class="mx-card__title">{{ __('Compte') }}</h2>
      <div class="mx-grid-2">
        <div class="mx-field"><label class="mx-label" for="st-email">{{ __('E-mail') }}</label><input class="mx-input" id="st-email" type="email" name="email" value="{{ old('email', $account->email) }}" maxlength="180">@error('email')<p class="mx-error">{{ $message }}</p>@enderror</div>
        <div class="mx-field"><label class="mx-label" for="st-phone">{{ __('Téléphone (identifiant de connexion)') }}</label><input class="mx-input" id="st-phone" value="{{ $account->phone ?: __('Non renseigné — connexion par e-mail') }}" disabled></div>
      </div>
      <div><button class="mx-btn mx-btn--primary">{{ __('Enregistrer') }}</button></div>
    </section>

    <section class="mx-card mx-card__pad mx-stack" id="confidentialite" style="{{ $anchor }}">
      <h2 class="mx-card__title">{{ __('Confidentialité') }}</h2>
      <fieldset class="mx-field" style="border:0;padding:0">
        <legend class="mx-label">{{ __('Visibilité du profil') }}</legend>
        <label class="mx-check"><input type="radio" name="profile_visibility" value="public" @checked(old('profile_visibility', $account->profile_visibility ?? 'public') === 'public')> {{ __('Public — visible par tous, trouvable dans la recherche') }}</label>
        <label class="mx-check"><input type="radio" name="profile_visibility" value="private" @checked(old('profile_visibility', $account->profile_visibility) === 'private')> {{ __('Privé — seuls votre nom et vos commentaires sont visibles') }}</label>
      </fieldset>
      <div><button class="mx-btn mx-btn--primary">{{ __('Enregistrer') }}</button></div>
    </section>

    <section class="mx-card mx-card__pad mx-stack" id="notifications" style="{{ $anchor }}">
      <h2 class="mx-card__title">{{ __('Notifications') }}</h2>
      <fieldset class="mx-field" style="border:0;padding:0">
        <legend class="mx-label">{{ __('E-mails') }}</legend>
        <input type="hidden" name="email_notifications" value="0">
        <label class="mx-check"><input type="checkbox" name="email_notifications" value="1" @checked(old('email_notifications', $account->email_notifications ?? true))> {{ __('Recevoir par e-mail les messages des mutuelles, l’avancement de mes demandes d’adhésion et mes admissions dans les groupes') }}</label>
        @unless ($account->email)<p class="mx-help">{{ __('Ajoutez une adresse e-mail dans la section Compte pour recevoir ces e-mails.') }}</p>@endunless
      </fieldset>
      <p class="mx-help">{{ __('Les notifications dans l’application restent toujours visibles dans Notifications.') }}</p>
      <div><button class="mx-btn mx-btn--primary">{{ __('Enregistrer') }}</button></div>
    </section>
  </form>

  <form class="mx-card mx-card__pad mx-stack" method="POST" action="{{ route('social.password') }}" id="securite" style="{{ $anchor }};margin-top:14px">
    @csrf
    <h2 class="mx-card__title">{{ __('Sécurité') }}</h2>
    <p class="mx-small mx-muted">{{ __('Changer le mot de passe') }}</p>
    <div class="mx-field"><label class="mx-label" for="pw-cur">{{ __('Mot de passe actuel') }}</label><input class="mx-input" id="pw-cur" type="password" name="current_password" required autocomplete="current-password">@error('current_password')<p class="mx-error">{{ $message }}</p>@enderror</div>
    <div class="mx-grid-2">
      <div class="mx-field"><label class="mx-label" for="pw-new">{{ __('Nouveau mot de passe') }}</label><input class="mx-input" id="pw-new" type="password" name="password" required minlength="8" autocomplete="new-password">@error('password')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="pw-conf">{{ __('Confirmation') }}</label><input class="mx-input" id="pw-conf" type="password" name="password_confirmation" required autocomplete="new-password"></div>
    </div>
    <div><button class="mx-btn mx-btn--ghost">{{ __('Changer le mot de passe') }}</button></div>
  </form>

  <section class="mx-card mx-card__pad mx-stack" id="langue" style="{{ $anchor }};margin-top:14px">
    <h2 class="mx-card__title">{{ __('Langue') }}</h2>
    <p class="mx-small mx-muted">{{ __('Langue de l’interface, enregistrée sur votre compte : vous la retrouvez sur tous vos appareils. Les montants gardent toujours leur devise.') }}</p>
    <x-lang-switcher variant="list" />
  </section>

  <section class="mx-card mx-card__pad mx-stack" id="applications" style="{{ $anchor }};margin-top:14px">
    <h2 class="mx-card__title">{{ __('Applications') }}</h2>
    <p class="mx-small mx-muted">{{ __('Installez MX-CONNECT sur votre téléphone ou votre ordinateur pour ouvrir le réseau en un geste.') }}</p>
    <div><a class="mx-btn mx-btn--ghost" href="{{ route('appcenter.index') }}">{{ __('Installer nos applications') }}</a></div>
  </section>

  <section class="mx-card mx-card__pad mx-stack" id="donnees" style="{{ $anchor }};margin-top:14px">
    <h2 class="mx-card__title">{{ __('Mes données') }}</h2>
    <p class="mx-small"><a href="{{ route('social.legal.history') }}">{{ __('Documents et historique de mes acceptations') }}</a></p>
    <form class="mx-stack" method="POST" action="{{ route('social.account.destroy') }}" data-confirm="{{ __('Supprimer définitivement votre compte ? Cette action est irréversible.') }}">
      @csrf
      <h3 id="supprimer" style="color:var(--mx-danger);font-size:16px;{{ $anchor }}">{{ __('Supprimer mon compte') }}</h3>
      <p class="mx-small">{{ __('Supprime définitivement votre profil, vos publications, commentaires, réactions, abonnements, messages, notifications et demandes d’adhésion (avec leurs pièces jointes). Une fiche d’adhérent déjà créée par une mutuelle reste dans sa gestion : adressez-vous à elle pour la clôturer.') }}</p>
      <div class="mx-grid-2">
        <div class="mx-field"><label class="mx-label" for="del-pw">{{ __('Mot de passe') }}</label><input class="mx-input" id="del-pw" type="password" name="current_password" required autocomplete="current-password">@error('current_password')<p class="mx-error">{{ $message }}</p>@enderror</div>
        <div class="mx-field"><label class="mx-label" for="del-confirm">{{ __('Tapez SUPPRIMER') }}</label><input class="mx-input" id="del-confirm" name="confirm" required autocomplete="off">@error('confirm')<p class="mx-error">{{ $message }}</p>@enderror</div>
      </div>
      <div><button class="mx-btn mx-btn--danger">{{ __('Supprimer définitivement mon compte') }}</button></div>
    </form>
  </section>
</x-layouts.social>
