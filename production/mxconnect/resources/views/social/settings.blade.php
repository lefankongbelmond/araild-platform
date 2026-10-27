<x-layouts.social title="Paramètres" active="parametres">
  <h1 style="font-size:22px">Paramètres du compte</h1>

  <form class="mx-card mx-card__pad mx-stack" method="POST" action="{{ route('social.settings.update') }}" enctype="multipart/form-data">
    @csrf
    <h2 class="mx-card__title">Profil</h2>
    <div class="mx-row" style="gap:14px">
      <x-mx.avatar :src="$account->avatarUrl()" :name="$account->name" size="lg" />
      <div class="mx-stack" style="gap:6px">
        <label class="mx-label" for="st-avatar">Photo de profil</label>
        <input id="st-avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">
        @if ($account->avatar_path)<label class="mx-check"><input type="checkbox" name="remove_avatar" value="1"> Retirer la photo</label>@endif
        @error('avatar')<p class="mx-error">{{ $message }}</p>@enderror
      </div>
    </div>
    <div class="mx-stack" style="gap:6px">
      <label class="mx-label" for="st-cover">Photo de couverture</label>
      @if ($account->cover_path)<div class="mx-cover has-img" style="height:110px;border-radius:12px;background-image:url('{{ $account->coverUrl() }}')"></div>@endif
      <input id="st-cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif">
      <p class="mx-help">Format paysage conseillé (environ 3 × 1). Convertie automatiquement en WebP.</p>
      @if ($account->cover_path)<label class="mx-check"><input type="checkbox" name="remove_cover" value="1"> Retirer la photo de couverture</label>@endif
      @error('cover')<p class="mx-error">{{ $message }}</p>@enderror
    </div>
    <div class="mx-grid-2">
      <div class="mx-field"><label class="mx-label" for="st-fn">Prénom</label><input class="mx-input" id="st-fn" name="first_name" value="{{ old('first_name', $account->first_name) }}" required maxlength="120">@error('first_name')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="st-ln">Nom</label><input class="mx-input" id="st-ln" name="last_name" value="{{ old('last_name', $account->last_name) }}" required maxlength="120">@error('last_name')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="st-email">E-mail</label><input class="mx-input" id="st-email" type="email" name="email" value="{{ old('email', $account->email) }}" maxlength="180">@error('email')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="st-city">Ville</label><input class="mx-input" id="st-city" name="city" value="{{ old('city', $account->city) }}" maxlength="120"></div>
    </div>
    <div class="mx-field"><label class="mx-label">Téléphone (identifiant de connexion)</label><input class="mx-input" value="{{ $account->phone ?: 'Non renseigné — connexion par e-mail' }}" disabled></div>
    <p class="mx-help"><a href="{{ route('social.legal.history') }}">Documents et historique de mes acceptations</a></p>
    <div class="mx-field"><label class="mx-label" for="st-bio">Présentation</label><textarea class="mx-textarea" id="st-bio" name="bio" maxlength="280" style="min-height:70px">{{ old('bio', $account->bio) }}</textarea><p class="mx-help">280 caractères maximum. N'indiquez pas d'information sur votre santé.</p></div>
    <fieldset class="mx-field" style="border:0;padding:0">
      <legend class="mx-label">Centres d'intérêt</legend>
      <div class="mx-grid-2" style="gap:6px">
        @foreach ($interests as $k => $v)
          <label class="mx-check"><input type="checkbox" name="interests[]" value="{{ $k }}" @checked(in_array($k, old('interests', $account->interests ?? []), true))> {{ $v }}</label>
        @endforeach
      </div>
    </fieldset>
    <fieldset class="mx-field" style="border:0;padding:0">
      <legend class="mx-label">Visibilité du profil</legend>
      <label class="mx-check"><input type="radio" name="profile_visibility" value="public" @checked(old('profile_visibility', $account->profile_visibility ?? 'public') === 'public')> Public — visible par tous, trouvable dans la recherche</label>
      <label class="mx-check"><input type="radio" name="profile_visibility" value="private" @checked(old('profile_visibility', $account->profile_visibility) === 'private')> Privé — seuls votre nom et vos commentaires sont visibles</label>
    </fieldset>
    <fieldset class="mx-field" style="border:0;padding:0">
      <legend class="mx-label">E-mails</legend>
      <input type="hidden" name="email_notifications" value="0">
      <label class="mx-check"><input type="checkbox" name="email_notifications" value="1" @checked(old('email_notifications', $account->email_notifications ?? true))> Recevoir par e-mail les messages des mutuelles, l’avancement de mes demandes d’adhésion et mes admissions dans les groupes</label>
      @unless ($account->email)<p class="mx-help">Ajoutez une adresse e-mail ci-dessus pour recevoir ces e-mails.</p>@endunless
    </fieldset>
    <div><button class="mx-btn mx-btn--primary">Enregistrer</button></div>
  </form>

  <form class="mx-card mx-card__pad mx-stack" method="POST" action="{{ route('social.password') }}">
    @csrf
    <h2 class="mx-card__title">Mot de passe</h2>
    <div class="mx-field"><label class="mx-label" for="pw-cur">Mot de passe actuel</label><input class="mx-input" id="pw-cur" type="password" name="current_password" required autocomplete="current-password">@error('current_password')<p class="mx-error">{{ $message }}</p>@enderror</div>
    <div class="mx-grid-2">
      <div class="mx-field"><label class="mx-label" for="pw-new">Nouveau mot de passe</label><input class="mx-input" id="pw-new" type="password" name="password" required minlength="8" autocomplete="new-password">@error('password')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="pw-conf">Confirmation</label><input class="mx-input" id="pw-conf" type="password" name="password_confirmation" required autocomplete="new-password"></div>
    </div>
    <div><button class="mx-btn mx-btn--ghost">Changer le mot de passe</button></div>
  </form>
  <form class="mx-card mx-card__pad mx-stack" method="POST" action="{{ route('social.account.destroy') }}" data-confirm="Supprimer définitivement votre compte ? Cette action est irréversible.">
    @csrf
    <h2 class="mx-card__title" id="supprimer" style="color:var(--mx-danger)">Supprimer mon compte</h2>
    <p class="mx-small">Supprime définitivement votre profil, vos publications, commentaires, réactions, abonnements, messages, notifications et demandes d’adhésion (avec leurs pièces jointes). Une fiche d’adhérent déjà créée par une mutuelle reste dans sa gestion : adressez-vous à elle pour la clôturer.</p>
    <div class="mx-grid-2">
      <div class="mx-field"><label class="mx-label" for="del-pw">Mot de passe</label><input class="mx-input" id="del-pw" type="password" name="current_password" required autocomplete="current-password">@error('current_password')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="del-confirm">Tapez SUPPRIMER</label><input class="mx-input" id="del-confirm" name="confirm" required autocomplete="off">@error('confirm')<p class="mx-error">{{ $message }}</p>@enderror</div>
    </div>
    <div><button class="mx-btn mx-btn--danger">Supprimer définitivement mon compte</button></div>
  </form>
</x-layouts.social>
