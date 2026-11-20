@php
  $R = \App\Models\Social\SocialRequest::class;
  $types = $kind === 'engagement' ? $R::ENGAGEMENT_TYPES : ($kind === 'support' ? $R::SUPPORT_TYPES : []);
  $title = match ($kind) {
      'engagement'  => __('Je m’engage'),
      'support'     => __('Soutenir ou devenir partenaire'),
      default       => $opportunity->isJob() ? 'Postuler' : 'Manifester mon intérêt',
  };
  $subject = $opportunity?->title ?? $project?->title;
  $back = $opportunity ? route('social.opportunity', $opportunity) : ($project ? route('social.project', $project) : route('social.mutual', $page->slug));
@endphp
<x-layouts.social :title="$title" active="demandes">
  <a class="mx-small" href="{{ $back }}"><x-mx.icon name="arrow-left" :size="14"/> {{ __('Retour') }}</a>
  <form class="mx-card mx-card__pad mx-stack" method="POST" action="{{ route('social.request.store') }}" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="type" value="{{ $kind }}">
    @if ($opportunity)<input type="hidden" name="offre" value="{{ $opportunity->slug }}">@endif
    @if ($project)<input type="hidden" name="projet" value="{{ $project->slug }}">@endif
    @if (! $opportunity && ! $project)<input type="hidden" name="page" value="{{ $page->slug }}">@endif

    <div class="mx-row" style="gap:12px">
      <x-mx.avatar :src="$page->profile?->logoUrl()" :name="$page->name" org />
      <div style="min-width:0"><h1 style="font-size:22px">{{ $title }}</h1>
        <p class="mx-small mx-muted">{{ $page->name }}@if($subject) · {{ $subject }}@endif</p></div>
    </div>
    @if ($errors->any())<div class="mx-alert mx-alert--err" role="alert">{{ __('Vérifiez les champs signalés.') }}</div>@endif

    @if ($project && $kind === 'engagement' && $project->volunteer_needs)
      <div class="mx-alert mx-alert--info"><b>{{ __('Ce que recherche l’organisation :') }}</b> {{ $project->volunteer_needs }}</div>
    @elseif ($project && $kind === 'support' && $project->partner_needs)
      <div class="mx-alert mx-alert--info"><b>{{ __('Soutien recherché :') }}</b> {{ $project->partner_needs }}</div>
    @endif

    @if ($types)
      {{-- Maquettes 11 et 13 : un choix par carte (aucun choix présélectionné). --}}
      <fieldset class="mx-field mx-rcards"><legend class="mx-label">{{ $kind === 'engagement' ? __('Comment souhaitez-vous contribuer ?') : __('Type de soutien') }} *</legend>
        @foreach ($types as $k => $v)
          <label @class(['mx-rcard', 'mx-rcard--sun' => $kind === 'support'])><input type="radio" name="subtype" value="{{ $k }}" @checked(old('subtype') === $k) required><span>{{ __($v) }}</span></label>
        @endforeach
        @error('subtype')<p class="mx-error">{{ $message }}</p>@enderror</fieldset>
    @endif
    @if ($kind === 'support')
      <p class="mx-xs mx-muted">{{ __('MX-CONNECT ne collecte ni ne transfère aucun fonds : un soutien financier se convient directement avec l’organisation, après son accord.') }}</p>
      <div class="mx-field"><label class="mx-label" for="rq-org">{{ __('Au nom de (structure, facultatif)') }}</label>
        <input class="mx-input" id="rq-org" name="organisation_name" value="{{ old('organisation_name') }}" maxlength="180" placeholder="{{ __('Ex. : Entreprise, ONG, fondation…') }}"></div>
    @endif
    @if ($kind === 'engagement')
      <div class="mx-field"><label class="mx-label" for="rq-av">{{ __('Vos disponibilités') }}</label>
        <input class="mx-input" id="rq-av" name="availability" value="{{ old('availability') }}" maxlength="180" placeholder="{{ __('Ex. : le samedi matin, 4 h par semaine…') }}"></div>
    @endif

    <div class="mx-field"><label class="mx-label" for="rq-msg">{{ $kind === 'application' ? __('Votre message de motivation') : __('Votre message') }} *</label>
      <textarea class="mx-textarea" id="rq-msg" name="message" required minlength="20" maxlength="3000" style="min-height:140px">{{ old('message') }}</textarea>
      <p class="mx-help">{{ __('N’indiquez aucune information médicale (maladie, traitement, résultats d’examens).') }}</p>
      @error('message')<p class="mx-error">{{ $message }}</p>@enderror</div>

    @if ($kind === 'application')
      <div class="mx-field"><label class="mx-label" for="rq-cv">{{ __('CV (PDF, facultatif)') }}</label>
        <input id="rq-cv" type="file" name="cv" accept="application/pdf">
        <p class="mx-help">{{ __('Visible seulement par vous et par les responsables de :name. :size Mo maximum.', ['name' => $page->name, 'size' => round(config('mxconnect.social.adhesion_doc_kb', 5120) / 1024)]) }}</p>
        @error('cv')<p class="mx-error">{{ $message }}</p>@enderror</div>
    @endif

    <h2 class="mx-card__title" style="margin-top:4px">{{ __('Vos coordonnées') }}</h2>
    <div class="mx-grid-2">
      <div class="mx-field"><label class="mx-label" for="rq-name">{{ __('Nom complet *') }}</label><input class="mx-input" id="rq-name" name="contact_name" value="{{ old('contact_name', $account->name) }}" required maxlength="180">@error('contact_name')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="rq-phone">{{ __('Téléphone') }}</label><input class="mx-input" id="rq-phone" name="contact_phone" value="{{ old('contact_phone', $account->phone) }}" maxlength="40" inputmode="tel">@error('contact_phone')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="rq-email">{{ __('E-mail') }}</label><input class="mx-input" id="rq-email" type="email" name="contact_email" value="{{ old('contact_email', $account->email) }}" maxlength="180">@error('contact_email')<p class="mx-error">{{ $message }}</p>@enderror</div>
    </div>

    <label class="mx-check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))> <span>{{ __('J’accepte que ma demande et mes coordonnées soient transmises à :name pour qu’elle me réponde.', ['name' => $page->name]) }}</span></label>
    @error('consent')<p class="mx-error">{{ $message }}</p>@enderror
    <label class="mx-check"><input type="checkbox" name="no_medical" value="1" required @checked(old('no_medical'))> <span>{{ __('Je confirme n’avoir indiqué aucune donnée médicale.') }}</span></label>
    @error('no_medical')<p class="mx-error">{{ $message }}</p>@enderror

    <div class="mx-row mx-row--wrap">
      <button @class(['mx-btn', 'mx-btn--primary' => $kind !== 'support', 'mx-btn--sunfill' => $kind === 'support'])><x-mx.icon name="send" :size="16"/> {{ $kind === 'application' ? __('Envoyer ma candidature') : __('Envoyer ma demande') }}</button>
      <a class="mx-btn mx-btn--ghost" href="{{ $back }}">{{ __('Annuler') }}</a>
    </div>
  </form>
</x-layouts.social>
