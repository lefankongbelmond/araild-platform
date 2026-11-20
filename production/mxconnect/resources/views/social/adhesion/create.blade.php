@php $R = \App\Models\Social\AdhesionRequest::class; @endphp
<x-layouts.social :title="'Adhésion · ' . $mutual->name" active="adhesions">
  <a class="mx-small" href="{{ route('social.mutual', [$mutual->slug, 'onglet' => 'offres']) }}"><x-mx.icon name="arrow-left" :size="14"/> {{ $mutual->name }}</a>
  <section class="mx-card mx-card__pad mx-stack">
    <div class="mx-row" style="gap:12px">
      <x-mx.avatar :src="$mutual->profile?->logoUrl()" :name="$mutual->name" org />
      <div><h1 style="font-size:20px">{{ __('Demande d’adhésion') }}</h1><p class="mx-small mx-muted">{{ $mutual->name }}</p></div>
    </div>
    @unless ($mutual->isVerified())<div class="mx-alert mx-alert--warn">{{ __('Cette mutuelle est en cours de vérification par le réseau MX-CONNECT.') }}</div>@endunless
    <p class="mx-small">{{ __('Votre demande est transmise uniquement à') }} <b>{{ $mutual->name }}</b>{{ __(', qui l’étudie et vous répond ici. L’adhésion n’est définitive qu’après acceptation par la mutuelle ; elle vous indiquera alors les modalités de paiement de la cotisation.') }} <b>{{ __('Aucun paiement n’est demandé en ligne.') }}</b></p>
    @if ($offer && $offer->monthly_contribution_min_minor !== null)
      <p class="mx-small mx-muted">{{ __('Cotisation mensuelle indicative :') }} {{ $offer->currency ? $offer->currency->format($offer->monthly_contribution_min_minor) : $offer->monthly_contribution_min_minor }}@if ($offer->monthly_contribution_max_minor > $offer->monthly_contribution_min_minor) – {{ $offer->currency ? $offer->currency->format($offer->monthly_contribution_max_minor) : $offer->monthly_contribution_max_minor }}@endif.</p>
    @endif
  </section>

  @if ($errors->any())<div class="mx-alert mx-alert--err" role="alert"><b>{{ __('Veuillez corriger :') }}</b><ul style="margin:6px 0 0 18px">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form class="mx-stack" method="POST" action="{{ route('social.adhesion.store', $mutual->slug) }}" enctype="multipart/form-data">
    @csrf
    <section class="mx-card mx-card__pad mx-stack">
      <h2 class="mx-card__title">{{ __('1. Le demandeur') }}</h2>
      <div class="mx-grid-2">
        <div class="mx-field"><label class="mx-label" for="a-fn">{{ __('Prénom') }}</label><input class="mx-input" id="a-fn" name="first_name" value="{{ old('first_name', $account->first_name) }}" required maxlength="120"></div>
        <div class="mx-field"><label class="mx-label" for="a-ln">{{ __('Nom') }}</label><input class="mx-input" id="a-ln" name="last_name" value="{{ old('last_name', $account->last_name) }}" required maxlength="120"></div>
        <div class="mx-field"><label class="mx-label" for="a-bd">{{ __('Date de naissance') }}</label><input class="mx-input" id="a-bd" type="date" name="birth_date" value="{{ old('birth_date') }}" required max="{{ now()->subDay()->toDateString() }}"></div>
        <div class="mx-field"><label class="mx-label" for="a-sex">{{ __('Sexe') }}</label><select class="mx-select" id="a-sex" name="sex" required><option value="">—</option><option value="F" @selected(old('sex') === 'F')>{{ __('Féminin') }}</option><option value="M" @selected(old('sex') === 'M')>{{ __('Masculin') }}</option></select></div>
        <div class="mx-field"><label class="mx-label" for="a-ph">{{ __('Téléphone') }}</label><input class="mx-input" id="a-ph" name="phone" value="{{ old('phone', $account->phone) }}" required></div>
        <div class="mx-field"><label class="mx-label" for="a-em">{{ __('E-mail (facultatif)') }}</label><input class="mx-input" id="a-em" type="email" name="email" value="{{ old('email', $account->email) }}"></div>
        <div class="mx-field"><label class="mx-label" for="a-city">{{ __('Ville') }}</label><input class="mx-input" id="a-city" name="city" value="{{ old('city', $account->city) }}" required maxlength="120"></div>
        <div class="mx-field"><label class="mx-label" for="a-prof">{{ __('Profession (facultatif)') }}</label><input class="mx-input" id="a-prof" name="profession" value="{{ old('profession') }}" maxlength="120"></div>
      </div>
      <div class="mx-field"><label class="mx-label" for="a-addr">{{ __('Adresse / quartier (facultatif)') }}</label><input class="mx-input" id="a-addr" name="address" value="{{ old('address') }}" maxlength="255"></div>
      <div class="mx-field"><label class="mx-label" for="a-idn">{{ __('N° de pièce d’identité (facultatif)') }}</label><input class="mx-input" id="a-idn" name="id_document_number" value="{{ old('id_document_number') }}" maxlength="60"></div>
    </section>

    <section class="mx-card mx-card__pad mx-stack">
      <h2 class="mx-card__title">{{ __('2. Le foyer') }}</h2>
      <div class="mx-grid-2">
        <div class="mx-field"><label class="mx-label" for="a-hh">{{ __('Nombre de personnes à couvrir (vous compris)') }}</label><input class="mx-input" id="a-hh" type="number" min="1" max="30" name="household_size" value="{{ old('household_size', 1) }}" required></div>
        <div class="mx-field"><label class="mx-label" for="a-form">{{ __('Formule souhaitée (facultatif)') }}</label><input class="mx-input" id="a-form" name="formula" value="{{ old('formula') }}" maxlength="180" placeholder="{{ __('Ex. : formule Famille') }}"></div>
      </div>
      <p class="mx-small mx-muted">{{ __('Bénéficiaires (facultatif — la mutuelle pourra les compléter avec vous) :') }}</p>
      @for ($i = 0; $i < 4; $i++)
        <div class="mx-grid-3" style="gap:8px">
          <input class="mx-input" name="beneficiaries[{{ $i }}][name]" value="{{ old("beneficiaries.$i.name") }}" placeholder="{{ __('Nom et prénom') }}" aria-label="Bénéficiaire {{ $i + 1 }} : nom" maxlength="180">
          <select class="mx-select" name="beneficiaries[{{ $i }}][relation]" aria-label="Bénéficiaire {{ $i + 1 }} : lien">@foreach ($R::RELATIONS as $k => $v)<option value="{{ $k }}" @selected(old("beneficiaries.$i.relation") === $k)>{{ __($v) }}</option>@endforeach</select>
          <input class="mx-input" type="date" name="beneficiaries[{{ $i }}][birth_date]" value="{{ old("beneficiaries.$i.birth_date") }}" aria-label="Bénéficiaire {{ $i + 1 }} : date de naissance">
        </div>
      @endfor
    </section>

    <section class="mx-card mx-card__pad mx-stack">
      <h2 class="mx-card__title">{{ __('3. Pièces justificatives (facultatif)') }}</h2>
      <p class="mx-small mx-muted">{{ __('PDF ou image JPEG, PNG, WebP — 4 pièces maximum, 5 Mo chacune. Elles sont conservées dans un espace privé, visible seulement par vous et les agents habilités de la mutuelle.') }}</p>
      @for ($i = 0; $i < 2; $i++)
        <div class="mx-row mx-row--wrap">
          <select class="mx-select" name="kinds[{{ $i }}]" style="width:auto" aria-label="Type de pièce {{ $i + 1 }}">@foreach ($R::DOC_KINDS as $k => $v)<option value="{{ $k }}" @selected($i === 0 ? $k === 'piece_identite' : $k === 'photo')>{{ __($v) }}</option>@endforeach</select>
          <input type="file" name="documents[{{ $i }}]" accept="application/pdf,image/jpeg,image/png,image/webp" aria-label="Fichier {{ $i + 1 }}">
        </div>
      @endfor
    </section>

    <section class="mx-card mx-card__pad mx-stack">
      <h2 class="mx-card__title">{{ __('4. Message et accord') }}</h2>
      <div class="mx-field"><label class="mx-label" for="a-msg">{{ __('Message à la mutuelle (facultatif)') }}</label><textarea class="mx-textarea" id="a-msg" name="message" maxlength="2000">{{ old('message') }}</textarea></div>
      <label class="mx-check"><input type="checkbox" name="no_medical" value="1" required @checked(old('no_medical'))> <span>{{ __('Je n’ai indiqué aucune information médicale (maladie, traitement, résultat d’examen). Ces informations ne sont pas demandées ici.') }}</span></label>
      <label class="mx-check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))> <span>{{ __('J’accepte que ces informations soient transmises à :name pour étudier ma demande d’adhésion (', ['name' => $mutual->name]) }}<a href="{{ route('social.privacy') }}" target="_blank">{{ __('confidentialité') }}</a>).</span></label>
      <div><button class="mx-btn mx-btn--primary mx-btn--lg"><x-mx.icon name="send" :size="16"/> {{ __('Envoyer ma demande') }}</button></div>
    </section>
  </form>
</x-layouts.social>
