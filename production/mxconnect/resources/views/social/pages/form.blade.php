<x-layouts.social :title="$editing ? 'Modifier la page' : 'Créer une page'" active="pages">
  <form class="mx-card mx-card__pad mx-stack" method="POST" enctype="multipart/form-data"
        action="{{ $editing ? route('social.pages.update', $mutual->slug) : route('social.pages.store') }}">
    @csrf
    <div>
      <h1 style="font-size:22px">{{ $editing ? 'Modifier « ' . $mutual->name . ' »' : 'Créer une page' }}</h1>
      <p class="mx-small mx-muted">La page présente votre organisation au public. Vous pourrez publier en son nom depuis le fil (« Publier en tant que »).</p>
    </div>
    @if ($errors->any())<div class="mx-alert mx-alert--err" role="alert">Vérifiez les champs signalés.</div>@endif

    <div class="mx-grid-2">
      <div class="mx-field"><label class="mx-label" for="pg-name">Nom de la page *</label>
        <input class="mx-input" id="pg-name" name="name" value="{{ old('name', $mutual->name) }}" required minlength="3" maxlength="120" placeholder="Ex. : Association Santé pour Tous">
        @error('name')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="pg-cat">Catégorie *</label>
        <select class="mx-select" id="pg-cat" name="category" required>
          @foreach (\App\Models\Social\MutualProfile::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('category', $profile->category) === $k)>{{ $v }}</option>@endforeach
        </select>@error('category')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="pg-country">Pays *</label>
        <select class="mx-select" id="pg-country" name="country_id" required>
          @foreach ($countries as $c)<option value="{{ $c->id }}" @selected((int) old('country_id', $mutual->country_id) === $c->id)>{{ $c->name }}</option>@endforeach
        </select>@error('country_id')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="pg-city">Ville</label><input class="mx-input" id="pg-city" name="city" value="{{ old('city', $mutual->city) }}" maxlength="120"></div>
      <div class="mx-field"><label class="mx-label" for="pg-region">Région</label><input class="mx-input" id="pg-region" name="region" value="{{ old('region', $mutual->region) }}" maxlength="120"></div>
      <div class="mx-field"><label class="mx-label" for="pg-tag">Slogan</label><input class="mx-input" id="pg-tag" name="tagline" value="{{ old('tagline', $profile->tagline) }}" maxlength="180" placeholder="Une phrase qui résume votre action"></div>
    </div>
    <div class="mx-field"><label class="mx-label" for="pg-desc">Présentation</label>
      <textarea class="mx-textarea" id="pg-desc" name="description" maxlength="5000" style="min-height:120px" data-emoji>{{ old('description', $profile->description) }}</textarea>
      @error('description')<p class="mx-error">{{ $message }}</p>@enderror</div>
    <div class="mx-grid-2">
      <div class="mx-field"><label class="mx-label" for="pg-phone">Téléphone</label><input class="mx-input" id="pg-phone" name="phone" value="{{ old('phone', $profile->phone) }}" maxlength="40"></div>
      <div class="mx-field"><label class="mx-label" for="pg-email">E-mail</label><input class="mx-input" id="pg-email" type="email" name="email" value="{{ old('email', $profile->email) }}" maxlength="180">@error('email')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="pg-web">Site web</label><input class="mx-input" id="pg-web" type="url" name="website" value="{{ old('website', $profile->website) }}" maxlength="255" placeholder="https://">@error('website')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="pg-addr">Adresse</label><input class="mx-input" id="pg-addr" name="address" value="{{ old('address', $profile->address) }}" maxlength="255"></div>
    </div>
    @include('social.partials.domain-picker', ['selected' => $profile->domains ?? []])
    <div class="mx-grid-2">
      <div class="mx-field"><label class="mx-label" for="pg-logo">Logo (photo de profil)</label>
        <input id="pg-logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif">
        @if ($profile->logo_path)<label class="mx-check mx-small"><input type="checkbox" name="remove_logo" value="1"> Retirer le logo actuel</label>@endif
        @error('logo')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="pg-cover">Photo de couverture</label>
        <input id="pg-cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif">
        @if ($profile->cover_path)<label class="mx-check mx-small"><input type="checkbox" name="remove_cover" value="1"> Retirer la couverture actuelle</label>@endif
        @error('cover')<p class="mx-error">{{ $message }}</p>@enderror</div>
    </div>
    @unless ($editing)
      <label class="mx-check"><input type="checkbox" name="accept" value="1" required @checked(old('accept'))> <span>Je confirme être autorisé(e) à représenter cette organisation et je respecterai les <a href="{{ route('social.about') }}" target="_blank">règles du réseau</a> (aucune information médicale personnelle).</span></label>
      @error('accept')<p class="mx-error">{{ $message }}</p>@enderror
    @endunless
    <div class="mx-row mx-row--wrap">
      <button class="mx-btn mx-btn--primary"><x-mx.icon name="check" :size="16"/> {{ $editing ? 'Enregistrer' : 'Créer la page' }}</button>
      <a class="mx-btn mx-btn--ghost" href="{{ $editing ? route('social.mutual', $mutual->slug) : route('social.pages.mine') }}">Annuler</a>
    </div>
  </form>

  @if ($editing)
    <section class="mx-card mx-card__pad mx-stack" id="gestionnaires">
      <h2 class="mx-card__title">Gestionnaires de la page</h2>
      <p class="mx-small mx-muted">Ces membres peuvent publier au nom de la page (choix « Publier en tant que »). Un administrateur peut aussi modifier la page et gérer cette liste.</p>
      <div class="mx-mini">
        @foreach ($managers as $mg)
          <div class="mx-mini__item">
            <x-mx.avatar :src="$mg->account?->avatarUrl()" :name="$mg->account?->name ?? '?'" size="sm" />
            <span class="mx-grow"><b>{{ $mg->account?->name }}</b><span class="mx-xs mx-muted">{{ \App\Models\Social\PageAdmin::ROLES[$mg->role] ?? $mg->role }}@if($mg->public_account_id === (int) $profile->owner_account_id) · créateur de la page @endif</span></span>
            @if ($mg->public_account_id !== (int) $profile->owner_account_id)
              <form method="POST" action="{{ route('social.pages.managers.remove', [$mutual->slug, $mg->id]) }}" data-confirm="Retirer ce gestionnaire ?">@csrf @method('DELETE')<button class="mx-btn mx-btn--ghost mx-btn--sm">Retirer</button></form>
            @endif
          </div>
        @endforeach
      </div>
      <form method="POST" action="{{ route('social.pages.managers.add', $mutual->slug) }}" class="mx-row mx-row--wrap" style="gap:8px;align-items:flex-end">
        @csrf
        <div class="mx-field mx-grow" style="min-width:200px"><label class="mx-label" for="mg-login">Téléphone ou e-mail du membre</label><input class="mx-input" id="mg-login" name="manager" required maxlength="180" placeholder="+237 6…"></div>
        <div class="mx-field"><label class="mx-label" for="mg-role">Rôle</label><select class="mx-select" id="mg-role" name="role">@foreach (\App\Models\Social\PageAdmin::ROLES as $k => $v)<option value="{{ $k }}" @selected($k === 'editor')>{{ $v }}</option>@endforeach</select></div>
        <button class="mx-btn mx-btn--primary"><x-mx.icon name="plus" :size="16"/> Ajouter</button>
      </form>
      @error('manager')<p class="mx-error">{{ $message }}</p>@enderror
    </section>

    <form class="mx-card mx-card__pad mx-stack" method="POST" action="{{ route('social.pages.destroy', $mutual->slug) }}">
      @csrf
      <h2 class="mx-card__title" style="color:var(--mx-red,#c62828)">Supprimer la page</h2>
      <p class="mx-small mx-muted">La page, ses publications et ses abonnés seront définitivement supprimés.</p>
      <div class="mx-field"><label class="mx-label" for="pg-confirm">Recopiez le nom de la page pour confirmer</label>
        <input class="mx-input" id="pg-confirm" name="confirm_name" autocomplete="off" placeholder="{{ $mutual->name }}">
        @error('confirm_name')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div><button class="mx-btn mx-btn--danger"><x-mx.icon name="trash" :size="16"/> Supprimer définitivement</button></div>
    </form>
  @endif
</x-layouts.social>
