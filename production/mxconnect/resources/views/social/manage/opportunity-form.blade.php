@php
  $O = \App\Models\Social\Opportunity::class;
  $editing = $o->exists;
  $action = $editing ? route('social.manage.opportunity.update', [$page->slug, $o->id]) : route('social.manage.opportunity.store', $page->slug);
@endphp
<x-layouts.social :title="$editing ? 'Modifier l’offre' : 'Nouvelle offre'" active="pages">
  @include('social.manage._head')

  <form class="mx-card mx-card__pad mx-stack" method="POST" action="{{ $action }}">
    @csrf
    <div class="mx-row mx-between mx-row--wrap" style="gap:8px">
      <h1 style="font-size:20px">{{ $editing ? $o->title : 'Nouvelle offre ou opportunité' }}</h1>
      @if ($editing)<a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.opportunity', $o) }}">Voir l’offre</a>@endif
    </div>
    @if ($o->status === 'hidden')<div class="mx-alert mx-alert--warn">Cette offre a été masquée par la modération du réseau.</div>@endif
    @if ($errors->any())<div class="mx-alert mx-alert--err" role="alert">Vérifiez les champs signalés.</div>@endif

    <div class="mx-grid-2">
      <div class="mx-field"><label class="mx-label" for="op-cat">Type d’opportunité *</label>
        <select class="mx-select" id="op-cat" name="category" required>@foreach ($O::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('category', $o->category) === $k)>{{ $v }}</option>@endforeach</select></div>
      <div class="mx-field"><label class="mx-label" for="op-contract">Contrat</label>
        <select class="mx-select" id="op-contract" name="contract_type"><option value="">—</option>@foreach ($O::CONTRACTS as $k => $v)<option value="{{ $k }}" @selected(old('contract_type', $o->contract_type) === $k)>{{ $v }}</option>@endforeach</select></div>
    </div>
    <div class="mx-field"><label class="mx-label" for="op-title">Intitulé *</label>
      <input class="mx-input" id="op-title" name="title" value="{{ old('title', $o->title) }}" required minlength="5" maxlength="180" placeholder="Ex. : Infirmier(ère) pour la tournée de dépistage">
      @error('title')<p class="mx-error">{{ $message }}</p>@enderror</div>
    <div class="mx-field"><label class="mx-label" for="op-desc">Description *</label>
      <textarea class="mx-textarea" id="op-desc" name="description" required minlength="20" maxlength="8000" style="min-height:140px">{{ old('description', $o->description) }}</textarea>
      <p class="mx-help">Missions, conditions, durée… Ne demandez jamais d’argent aux candidats.</p>@error('description')<p class="mx-error">{{ $message }}</p>@enderror</div>
    <div class="mx-field"><label class="mx-label" for="op-req">Profil recherché</label>
      <textarea class="mx-textarea" id="op-req" name="requirements" maxlength="4000">{{ old('requirements', $o->requirements) }}</textarea></div>

    <div class="mx-grid-2">
      <div class="mx-field"><label class="mx-label" for="op-work">Lieu de travail</label>
        <select class="mx-select" id="op-work" name="workplace"><option value="">—</option>@foreach ($O::WORKPLACES as $k => $v)<option value="{{ $k }}" @selected(old('workplace', $o->workplace) === $k)>{{ $v }}</option>@endforeach</select></div>
      <div class="mx-field"><label class="mx-label" for="op-country">Pays</label>
        <select class="mx-select" id="op-country" name="country_id"><option value="">—</option>@foreach ($countries as $c)<option value="{{ $c->id }}" @selected((int) old('country_id', $o->country_id) === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
      <div class="mx-field"><label class="mx-label" for="op-region">Région</label><input class="mx-input" id="op-region" name="region" value="{{ old('region', $o->region) }}" maxlength="120"></div>
      <div class="mx-field"><label class="mx-label" for="op-city">Ville</label><input class="mx-input" id="op-city" name="city" value="{{ old('city', $o->city) }}" maxlength="120"></div>
      <div class="mx-field"><label class="mx-label" for="op-pay">Rémunération</label><input class="mx-input" id="op-pay" name="remuneration" value="{{ old('remuneration', $o->remuneration) }}" maxlength="120" placeholder="Ex. : 150 000 XAF / mois, selon profil, non rémunéré">
        <p class="mx-help">Texte libre, dans votre devise (aucune conversion).</p></div>
      <div class="mx-field"><label class="mx-label" for="op-pos">Nombre de postes / places</label><input class="mx-input" id="op-pos" type="number" min="1" max="5000" name="positions" value="{{ old('positions', $o->positions) }}"></div>
      <div class="mx-field"><label class="mx-label" for="op-dead">Date limite</label><input class="mx-input" id="op-dead" type="date" name="deadline_on" value="{{ old('deadline_on', $o->deadline_on?->format('Y-m-d')) }}"></div>
      @if ($pageProjects->isNotEmpty())
        <div class="mx-field"><label class="mx-label" for="op-proj">Projet lié</label>
          <select class="mx-select" id="op-proj" name="project_id"><option value="">Aucun</option>@foreach ($pageProjects as $id => $t)<option value="{{ $id }}" @selected((int) old('project_id', $o->project_id) === $id)>{{ \Illuminate\Support\Str::limit($t, 60) }}</option>@endforeach</select></div>
      @endif
    </div>

    <fieldset class="mx-stack" style="border:1px solid var(--mx-border);border-radius:12px;padding:12px 14px">
      <legend class="mx-label" style="padding:0 6px">Candidatures</legend>
      @foreach ($O::APPLY_MODES as $k => $v)
        <label class="mx-check"><input type="radio" name="apply_mode" value="{{ $k }}" @checked(old('apply_mode', $o->apply_mode) === $k)> <span>{{ $v }}@if($k === 'platform') — vous les recevez dans « Demandes reçues » (CV en PDF)@endif</span></label>
      @endforeach
      <div class="mx-field"><label class="mx-label" for="op-url">Adresse du site de candidature</label><input class="mx-input" id="op-url" type="url" name="apply_url" value="{{ old('apply_url', $o->apply_url) }}" maxlength="255" placeholder="https://">@error('apply_url')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="op-ins">Consignes</label><input class="mx-input" id="op-ins" name="apply_instructions" value="{{ old('apply_instructions', $o->apply_instructions) }}" maxlength="500" placeholder="Ex. : joindre un CV et une lettre de motivation"></div>
    </fieldset>

    <div class="mx-grid-2">
      <div class="mx-field"><label class="mx-label" for="op-status">Visibilité</label>
        <select class="mx-select" id="op-status" name="status">
          <option value="published" @selected(old('status', $o->status === 'draft' ? 'draft' : 'published') === 'published')>Publiée (visible par tous)</option>
          <option value="draft" @selected(old('status', $o->status) === 'draft')>Brouillon</option>
        </select></div>
      @unless ($o->published_at)
        <label class="mx-check" style="align-self:end"><input type="checkbox" name="announce" value="1" @checked(old('announce', true))> <span>Annoncer l’offre dans le fil à sa publication</span></label>
      @endunless
    </div>
    <div class="mx-row mx-row--wrap">
      <button class="mx-btn mx-btn--primary"><x-mx.icon name="check" :size="16"/> Enregistrer</button>
      <a class="mx-btn mx-btn--ghost" href="{{ route('social.manage.opportunities', $page->slug) }}">Annuler</a>
    </div>
  </form>

  @if ($editing)
    <form method="POST" action="{{ route('social.manage.opportunity.destroy', [$page->slug, $o->id]) }}" data-confirm="Supprimer cette offre ? Les candidatures déjà reçues restent consultables." style="padding:0 4px">@csrf
      <button class="mx-btn mx-btn--ghost mx-btn--sm"><x-mx.icon name="trash" :size="14"/> Supprimer l’offre</button></form>
  @endif
</x-layouts.social>
