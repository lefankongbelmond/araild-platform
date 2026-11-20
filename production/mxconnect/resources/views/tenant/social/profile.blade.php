@php $field = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm focus:border-pine focus:outline-none'; @endphp
<x-layouts.tenant title="{{ __('Page publique') }}">
  <h1 class="text-2xl font-semibold tracking-tight">{{ __('Page publique de :name', ['name' => $mutual->name]) }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('Ces informations sont visibles par tous sur MX-CONNECT. N\'y mettez aucune donnée personnelle d\'adhérent.') }}</p>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <form method="POST" action="{{ route('social-presence.profile.update') }}" enctype="multipart/form-data" class="mt-6 grid gap-6 lg:grid-cols-3">
    @csrf
    <div class="space-y-4 rounded-lg border border-ink/10 bg-white p-5 lg:col-span-2">
      <label class="block text-sm font-medium">{{ __('Catégorie') }}
        <select name="category" class="{{ $field }}">@foreach (\App\Models\Social\MutualProfile::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('category', $profile->category) === $k)>{{ __($v) }}</option>@endforeach</select></label>
      <label class="block text-sm font-medium">{{ __('Accroche (une phrase)') }}<input name="tagline" maxlength="180" value="{{ old('tagline', $profile->tagline) }}" class="{{ $field }}"></label>
      <label class="block text-sm font-medium">{{ __('Présentation') }}<textarea name="description" rows="6" maxlength="5000" class="{{ $field }}">{{ old('description', $profile->description) }}</textarea></label>
      <label class="block text-sm font-medium">{{ __('Mission') }}<textarea name="mission" rows="3" maxlength="2000" class="{{ $field }}">{{ old('mission', $profile->mission) }}</textarea></label>
@php $selD = old('domains', $profile->domains ?? []) ?: []; @endphp
    <fieldset class="text-sm"><legend class="font-medium">{{ __('Domaines d’intervention') }}</legend>
      <div class="mt-2 flex flex-wrap gap-2">@foreach (\App\Support\SocialDomains::ALL as $code => $label)<label class="flex items-center gap-2 rounded-md border border-ink/10 bg-white px-3 py-1.5"><input type="checkbox" name="domains[]" value="{{ $code }}" @checked(in_array($code, (array) $selD, true))> {{ __($label) }}</label>@endforeach</div></fieldset>
      <label class="block text-sm font-medium">{{ __('Services (séparés par des virgules)') }}<input name="services" value="{{ old('services', implode(', ', $profile->services ?? [])) }}" class="{{ $field }}" placeholder="{{ __('Consultations, Maternité, Pharmacie') }}"></label>
      <label class="block text-sm font-medium">{{ __('Zone couverte') }}<input name="coverage_zone" maxlength="255" value="{{ old('coverage_zone', $profile->coverage_zone) }}" class="{{ $field }}"></label>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-medium">{{ __('Téléphone public') }}<input name="phone" maxlength="40" value="{{ old('phone', $profile->phone) }}" class="{{ $field }}"></label>
        <label class="block text-sm font-medium">{{ __('E-mail public') }}<input type="email" name="email" maxlength="180" value="{{ old('email', $profile->email) }}" class="{{ $field }}"></label>
        <label class="block text-sm font-medium">{{ __('Site web') }}<input type="url" name="website" maxlength="255" value="{{ old('website', $profile->website) }}" class="{{ $field }}" placeholder="https://"></label>
        <label class="block text-sm font-medium">{{ __('Adresse') }}<input name="address" maxlength="255" value="{{ old('address', $profile->address) }}" class="{{ $field }}"></label>
      </div>
      <label class="block text-sm font-medium">{{ __('E-mail d’alerte (privé, jamais affiché)') }}
        <input type="email" name="alert_email" maxlength="180" value="{{ old('alert_email', $profile->alert_email) }}" class="{{ $field }}" placeholder="ex. adhesions@votre-mutuelle.org">
        <span class="mt-1 block text-xs font-normal text-ink/50">{{ __('Reçoit une alerte (sans données personnelles) à chaque nouvelle demande d’adhésion, nouveau message d’un membre ou demande d’entrée dans un groupe privé.') }}@unless (\App\Support\MailStatus::configured()) <b>{{ __('L’envoi d’e-mails n’est pas encore configuré sur le site.') }}</b>@endunless</span></label>
    </div>
    <div class="space-y-4">
      <div class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <div class="font-medium">{{ __('Logo') }}</div>
        @if ($profile->logo_path)<img src="{{ $central . $profile->logoUrl() }}" alt="" class="mt-2 h-20 w-20 rounded-lg object-cover"><label class="mt-2 flex items-center gap-2"><input type="checkbox" name="remove_logo" value="1"> {{ __('Retirer') }}</label>@endif
        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif" class="mt-2 block w-full text-xs">
        <p class="mt-1 text-xs text-ink/45">{{ __('Carré conseillé, JPEG/PNG/WebP/GIF, 6 Mo max. Convertie automatiquement en WebP.') }}</p>
      </div>
      <div class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <div class="font-medium">{{ __('Image de couverture') }}</div>
        @if ($profile->cover_path)<img src="{{ $central . $profile->coverUrl() }}" alt="" class="mt-2 h-24 w-full rounded-lg object-cover"><label class="mt-2 flex items-center gap-2"><input type="checkbox" name="remove_cover" value="1"> {{ __('Retirer') }}</label>@endif
        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif" class="mt-2 block w-full text-xs">
        <p class="mt-1 text-xs text-ink/45">{{ __('Format paysage (3:1 conseillé).') }}</p>
      </div>
      <button class="w-full rounded-md bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-navy">{{ __('Enregistrer la page') }}</button>
      <a href="{{ route('social-presence.index') }}" class="block text-center text-sm text-ink/60 hover:underline">{{ __('Annuler') }}</a>
    </div>
  </form>

  <section class="mt-6 rounded-lg border border-ink/10 bg-white p-5 text-sm" id="gestionnaires">
    <h2 class="text-base font-semibold">{{ __('Publier depuis le réseau social') }}</h2>
    <p class="mt-1 text-ink/60">{{ __('Ajoutez les comptes MX-CONNECT (téléphone ou e-mail) autorisés à publier au nom de :name directement depuis le fil du réseau social, avec le choix « Publier en tant que ».', ['name' => $mutual->name]) }}</p>
    @error('manager')<p class="mt-2 text-clay">{{ $message }}</p>@enderror
    <ul class="mt-3 divide-y divide-ink/5">
      @forelse ($managers as $mg)
        <li class="flex items-center justify-between gap-3 py-2"><span><b>{{ $mg->account?->name }}</b> <span class="text-ink/50">· {{ __(\App\Models\Social\PageAdmin::ROLES[$mg->role] ?? $mg->role) }}</span></span>
          <form method="POST" action="{{ route('social-presence.managers.remove', $mg->id) }}" onsubmit="return confirm('Retirer ce gestionnaire ?')">@csrf @method('DELETE')<button class="rounded border border-ink/15 px-3 py-1 text-xs hover:border-clay hover:text-clay">{{ __('Retirer') }}</button></form></li>
      @empty
        <li class="py-2 text-ink/50">{{ __('Aucun compte pour l’instant : les publications se font uniquement depuis ce back-office.') }}</li>
      @endforelse
    </ul>
    <form method="POST" action="{{ route('social-presence.managers.add') }}" class="mt-3 flex flex-wrap items-end gap-2">
      @csrf
      <label class="block grow font-medium">{{ __('Téléphone ou e-mail') }}<input name="manager" required maxlength="180" class="mt-1 block w-full rounded-md border border-ink/15 px-3 py-2" placeholder="+237 6…"></label>
      <label class="block font-medium">{{ __('Rôle') }}<select name="role" class="mt-1 block rounded-md border border-ink/15 px-3 py-2">@foreach (\App\Models\Social\PageAdmin::ROLES as $k => $v)<option value="{{ $k }}" @selected($k === 'editor')>{{ __($v) }}</option>@endforeach</select></label>
      <button class="rounded-md bg-pine px-4 py-2 font-medium text-white hover:bg-navy">{{ __('Ajouter') }}</button>
    </form>
  </section>
</x-layouts.tenant>
