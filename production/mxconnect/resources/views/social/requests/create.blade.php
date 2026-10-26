@php
  $R = \App\Models\Social\SocialRequest::class;
  $types = $kind === 'engagement' ? $R::ENGAGEMENT_TYPES : ($kind === 'support' ? $R::SUPPORT_TYPES : []);
  $title = match ($kind) {
      'engagement'  => 'Je m’engage',
      'support'     => 'Soutenir ou devenir partenaire',
      default       => $opportunity->isJob() ? 'Postuler' : 'Manifester mon intérêt',
  };
  $subject = $opportunity?->title ?? $project?->title;
  $back = $opportunity ? route('social.opportunity', $opportunity) : ($project ? route('social.project', $project) : route('social.mutual', $page->slug));
@endphp
<x-layouts.social :title="$title" active="demandes">
  <a class="mx-small" href="{{ $back }}"><x-mx.icon name="arrow-left" :size="14"/> Retour</a>
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
    @if ($errors->any())<div class="mx-alert mx-alert--err" role="alert">Vérifiez les champs signalés.</div>@endif

    @if ($project && $kind === 'engagement' && $project->volunteer_needs)
      <div class="mx-alert mx-alert--info"><b>Ce que recherche l’organisation :</b> {{ $project->volunteer_needs }}</div>
    @elseif ($project && $kind === 'support' && $project->partner_needs)
      <div class="mx-alert mx-alert--info"><b>Soutien recherché :</b> {{ $project->partner_needs }}</div>
    @endif

    @if ($types)
      <div class="mx-field"><label class="mx-label" for="rq-sub">{{ $kind === 'engagement' ? 'Forme d’engagement' : 'Type de soutien' }} *</label>
        <select class="mx-select" id="rq-sub" name="subtype" required>
          <option value="">Choisissez…</option>
          @foreach ($types as $k => $v)<option value="{{ $k }}" @selected(old('subtype') === $k)>{{ $v }}</option>@endforeach
        </select>@error('subtype')<p class="mx-error">{{ $message }}</p>@enderror</div>
    @endif
    @if ($kind === 'support')
      <p class="mx-xs mx-muted">MX-CONNECT ne collecte ni ne transfère aucun fonds : un soutien financier se convient directement avec l’organisation, après son accord.</p>
      <div class="mx-field"><label class="mx-label" for="rq-org">Au nom de (structure, facultatif)</label>
        <input class="mx-input" id="rq-org" name="organisation_name" value="{{ old('organisation_name') }}" maxlength="180" placeholder="Ex. : Entreprise, ONG, fondation…"></div>
    @endif
    @if ($kind === 'engagement')
      <div class="mx-field"><label class="mx-label" for="rq-av">Vos disponibilités</label>
        <input class="mx-input" id="rq-av" name="availability" value="{{ old('availability') }}" maxlength="180" placeholder="Ex. : le samedi matin, 4 h par semaine…"></div>
    @endif

    <div class="mx-field"><label class="mx-label" for="rq-msg">{{ $kind === 'application' ? 'Votre message de motivation' : 'Votre message' }} *</label>
      <textarea class="mx-textarea" id="rq-msg" name="message" required minlength="20" maxlength="3000" style="min-height:140px">{{ old('message') }}</textarea>
      <p class="mx-help">N’indiquez aucune information médicale (maladie, traitement, résultats d’examens).</p>
      @error('message')<p class="mx-error">{{ $message }}</p>@enderror</div>

    @if ($kind === 'application')
      <div class="mx-field"><label class="mx-label" for="rq-cv">CV (PDF, facultatif)</label>
        <input id="rq-cv" type="file" name="cv" accept="application/pdf">
        <p class="mx-help">Visible seulement par vous et par les responsables de {{ $page->name }}. {{ round(config('mxconnect.social.adhesion_doc_kb', 5120) / 1024) }} Mo maximum.</p>
        @error('cv')<p class="mx-error">{{ $message }}</p>@enderror</div>
    @endif

    <h2 class="mx-card__title" style="margin-top:4px">Vos coordonnées</h2>
    <div class="mx-grid-2">
      <div class="mx-field"><label class="mx-label" for="rq-name">Nom complet *</label><input class="mx-input" id="rq-name" name="contact_name" value="{{ old('contact_name', $account->name) }}" required maxlength="180">@error('contact_name')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="rq-phone">Téléphone</label><input class="mx-input" id="rq-phone" name="contact_phone" value="{{ old('contact_phone', $account->phone) }}" maxlength="40" inputmode="tel">@error('contact_phone')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="rq-email">E-mail</label><input class="mx-input" id="rq-email" type="email" name="contact_email" value="{{ old('contact_email', $account->email) }}" maxlength="180">@error('contact_email')<p class="mx-error">{{ $message }}</p>@enderror</div>
    </div>

    <label class="mx-check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))> <span>J’accepte que ma demande et mes coordonnées soient transmises à {{ $page->name }} pour qu’elle me réponde.</span></label>
    @error('consent')<p class="mx-error">{{ $message }}</p>@enderror
    <label class="mx-check"><input type="checkbox" name="no_medical" value="1" required @checked(old('no_medical'))> <span>Je confirme n’avoir indiqué aucune donnée médicale.</span></label>
    @error('no_medical')<p class="mx-error">{{ $message }}</p>@enderror

    <div class="mx-row mx-row--wrap">
      <button class="mx-btn mx-btn--primary"><x-mx.icon name="send" :size="16"/> Envoyer</button>
      <a class="mx-btn mx-btn--ghost" href="{{ $back }}">Annuler</a>
    </div>
  </form>
</x-layouts.social>
