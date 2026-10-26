@php
  $editing = $project->exists;
  $I = \App\Models\Social\ProjectIndicator::class;
  $action = $editing ? route('social.manage.project.update', [$page->slug, $project->id]) : route('social.manage.project.store', $page->slug);
@endphp
<x-layouts.social :title="$editing ? 'Modifier le projet' : 'Nouveau projet'" active="pages">
  @include('social.manage._head')

  <form class="mx-card mx-card__pad mx-stack" method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    <div class="mx-row mx-between mx-row--wrap" style="gap:8px">
      <h1 style="font-size:20px">{{ $editing ? $project->title : 'Nouveau projet' }}</h1>
      @if ($editing)<a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.project', $project) }}">Voir la page du projet</a>@endif
    </div>
    @if ($project->status === 'hidden')<div class="mx-alert mx-alert--warn">Ce projet a été masqué par la modération du réseau. Contactez l’administration du réseau pour en savoir plus.</div>@endif
    @if ($errors->any())<div class="mx-alert mx-alert--err" role="alert">Vérifiez les champs signalés.</div>@endif

    <div class="mx-field"><label class="mx-label" for="pj-title">Titre du projet *</label>
      <input class="mx-input" id="pj-title" name="title" value="{{ old('title', $project->title) }}" required minlength="5" maxlength="180" placeholder="Ex. : Dépistage du diabète dans 10 villages">
      @error('title')<p class="mx-error">{{ $message }}</p>@enderror</div>
    <div class="mx-field"><label class="mx-label" for="pj-sum">Résumé *</label>
      <textarea class="mx-textarea" id="pj-sum" name="summary" required minlength="10" maxlength="300" style="min-height:70px">{{ old('summary', $project->summary) }}</textarea>
      <p class="mx-help">Une ou deux phrases affichées dans les listes (300 caractères).</p>@error('summary')<p class="mx-error">{{ $message }}</p>@enderror</div>
    <div class="mx-field"><label class="mx-label" for="pj-desc">Description</label>
      <textarea class="mx-textarea" id="pj-desc" name="description" maxlength="8000" style="min-height:120px">{{ old('description', $project->description) }}</textarea>@error('description')<p class="mx-error">{{ $message }}</p>@enderror</div>
    <div class="mx-field"><label class="mx-label" for="pj-obj">Objectifs</label>
      <textarea class="mx-textarea" id="pj-obj" name="objectives" maxlength="3000">{{ old('objectives', $project->objectives) }}</textarea>@error('objectives')<p class="mx-error">{{ $message }}</p>@enderror</div>

    <div class="mx-grid-2">
      <div class="mx-field"><label class="mx-label" for="pj-phase">État *</label>
        <select class="mx-select" id="pj-phase" name="phase" required>@foreach (\App\Models\Social\Project::PHASES as $k => $v)<option value="{{ $k }}" @selected(old('phase', $project->phase) === $k)>{{ $v }}</option>@endforeach</select></div>
      <div class="mx-field"><label class="mx-label" for="pj-zone">Zone d’intervention</label><input class="mx-input" id="pj-zone" name="zone" value="{{ old('zone', $project->zone) }}" maxlength="255" placeholder="Ex. : District de santé de Mbalmayo"></div>
      <div class="mx-field"><label class="mx-label" for="pj-region">Région</label><input class="mx-input" id="pj-region" name="region" value="{{ old('region', $project->region ?? $page->region) }}" maxlength="120"></div>
      <div class="mx-field"><label class="mx-label" for="pj-city">Ville</label><input class="mx-input" id="pj-city" name="city" value="{{ old('city', $project->city ?? $page->city) }}" maxlength="120"></div>
      <div class="mx-field"><label class="mx-label" for="pj-start">Début</label><input class="mx-input" id="pj-start" type="date" name="starts_on" value="{{ old('starts_on', $project->starts_on?->format('Y-m-d')) }}"></div>
      <div class="mx-field"><label class="mx-label" for="pj-end">Fin</label><input class="mx-input" id="pj-end" type="date" name="ends_on" value="{{ old('ends_on', $project->ends_on?->format('Y-m-d')) }}">@error('ends_on')<p class="mx-error">{{ $message }}</p>@enderror</div>
    </div>

    <fieldset class="mx-stack" style="border:1px solid var(--mx-border);border-radius:12px;padding:12px 14px">
      <legend class="mx-label" style="padding:0 6px">Besoins (affichent les boutons d’action)</legend>
      <label class="mx-check"><input type="hidden" name="seeks_volunteers" value="0"><input type="checkbox" name="seeks_volunteers" value="1" @checked(old('seeks_volunteers', $project->seeks_volunteers))> <span>Nous cherchons des bénévoles — bouton « Je m’engage »</span></label>
      <div class="mx-field"><label class="mx-label" for="pj-vn">Ce que les bénévoles pourront faire</label><textarea class="mx-textarea" id="pj-vn" name="volunteer_needs" maxlength="2000" style="min-height:70px">{{ old('volunteer_needs', $project->volunteer_needs) }}</textarea>@error('volunteer_needs')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <label class="mx-check"><input type="hidden" name="seeks_partners" value="0"><input type="checkbox" name="seeks_partners" value="1" @checked(old('seeks_partners', $project->seeks_partners))> <span>Nous cherchons des partenaires — bouton « Soutenir ou devenir partenaire »</span></label>
      <div class="mx-field"><label class="mx-label" for="pj-pn">Soutien recherché</label><textarea class="mx-textarea" id="pj-pn" name="partner_needs" maxlength="2000" style="min-height:70px" placeholder="Ex. : matériel de dépistage, formation des relais, transport…">{{ old('partner_needs', $project->partner_needs) }}</textarea>
        <p class="mx-help">Aucun paiement ne passe par MX-CONNECT : un soutien financier se convient directement avec vous.</p>@error('partner_needs')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="pj-partners">Partenaires actuels</label><textarea class="mx-textarea" id="pj-partners" name="partners" maxlength="2000" style="min-height:60px">{{ old('partners', $project->partners) }}</textarea>
        <p class="mx-help">Citez seulement des partenaires qui ont accepté d’être mentionnés.</p></div>
    </fieldset>

    <div class="mx-field"><label class="mx-label" for="pj-cover">Photo de couverture</label>
      <input id="pj-cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif">
      @if ($project->cover_path)<label class="mx-check mx-small"><input type="checkbox" name="remove_cover" value="1"> Retirer la photo actuelle</label>@endif
      @error('cover')<p class="mx-error">{{ $message }}</p>@enderror</div>

    <div class="mx-grid-2">
      <div class="mx-field"><label class="mx-label" for="pj-status">Visibilité</label>
        <select class="mx-select" id="pj-status" name="status">
          <option value="published" @selected(old('status', $project->status === 'draft' ? 'draft' : 'published') === 'published')>Publié (visible par tous)</option>
          <option value="draft" @selected(old('status', $project->status) === 'draft')>Brouillon (visible par les gestionnaires)</option>
        </select></div>
      @unless ($project->published_at)
        <label class="mx-check" style="align-self:end"><input type="checkbox" name="announce" value="1" @checked(old('announce', true))> <span>Annoncer le projet dans le fil à sa publication</span></label>
      @endunless
    </div>
    <div class="mx-row mx-row--wrap">
      <button class="mx-btn mx-btn--primary"><x-mx.icon name="check" :size="16"/> Enregistrer</button>
      <a class="mx-btn mx-btn--ghost" href="{{ route('social.manage.projects', $page->slug) }}">Annuler</a>
    </div>
  </form>

  @if ($editing)
    <section class="mx-card mx-card__pad mx-stack" id="resultats">
      <h2 class="mx-card__title">Résultats chiffrés</h2>
      <p class="mx-small mx-muted">Publiez uniquement des chiffres réels, avec leur source. Ils s’affichent « Déclaré » ; l’administration du réseau peut les marquer « Vérifié » après contrôle. Modifier un chiffre vérifié annule sa vérification.</p>
      @if ($errors->indicator->any())<div class="mx-alert mx-alert--err" role="alert">{{ $errors->indicator->first() }}</div>@endif
      @foreach ($project->indicators as $i)
        <details class="mx-card" style="padding:10px 12px;box-shadow:none;border:1px solid var(--mx-border)">
          <summary style="cursor:pointer;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><b>{{ $I::fmt($i->value) }} {{ $i->unit }}</b> <span>{{ $i->label }}</span>
            <span class="mx-badge {{ $i->isVerified() ? 'mx-badge--verified' : 'mx-badge--declared' }}">{{ $i->isVerified() ? 'Vérifié' : 'Déclaré' }}</span></summary>
          <form method="POST" action="{{ route('social.manage.indicator.update', [$page->slug, $project->id, $i->id]) }}" class="mx-inline-form" style="margin-top:10px">@csrf
            @include('social.manage._indicator-fields', ['i' => $i, 'p' => 'ind' . $i->id])
            <div class="mx-row mx-row--wrap mx-span-all"><button class="mx-btn mx-btn--primary mx-btn--sm">Enregistrer</button></div>
          </form>
          <form method="POST" action="{{ route('social.manage.indicator.destroy', [$page->slug, $project->id, $i->id]) }}" data-confirm="Supprimer ce résultat ?" style="margin-top:6px">@csrf<button class="mx-btn mx-btn--ghost mx-btn--sm"><x-mx.icon name="trash" :size="14"/> Supprimer</button></form>
        </details>
      @endforeach
      <form method="POST" action="{{ route('social.manage.indicator.store', [$page->slug, $project->id]) }}" class="mx-inline-form" style="border-top:1px solid var(--mx-border);padding-top:12px">@csrf
        <b class="mx-span-all">Ajouter un résultat</b>
        @include('social.manage._indicator-fields', ['i' => new \App\Models\Social\ProjectIndicator(), 'p' => 'new'])
        <div class="mx-span-all"><button class="mx-btn mx-btn--soft mx-btn--sm"><x-mx.icon name="plus" :size="14"/> Ajouter</button></div>
      </form>
    </section>

    <section class="mx-card mx-card__pad mx-stack" id="actualites">
      <h2 class="mx-card__title">Actualités d’avancement</h2>
      @if ($errors->progress->any())<div class="mx-alert mx-alert--err" role="alert">{{ $errors->progress->first() }}</div>@endif
      <form method="POST" action="{{ route('social.manage.update.store', [$page->slug, $project->id]) }}" class="mx-stack">@csrf
        <div class="mx-field"><label class="mx-label" for="up-title">Titre</label><input class="mx-input" id="up-title" name="update_title" value="{{ old('update_title') }}" required maxlength="180" placeholder="Ex. : 2e campagne de dépistage terminée"></div>
        <div class="mx-field"><label class="mx-label" for="up-body">Texte</label><textarea class="mx-textarea" id="up-body" name="update_body" required maxlength="5000">{{ old('update_body') }}</textarea>
          <p class="mx-help">Aucune information médicale personnelle, aucune photo de personne sans son accord.</p></div>
        <label class="mx-check"><input type="checkbox" name="announce" value="1" @checked($project->status === 'published')> <span>Publier aussi dans le fil</span></label>
        <div><button class="mx-btn mx-btn--soft mx-btn--sm"><x-mx.icon name="send" :size="14"/> Publier l’actualité</button></div>
      </form>
      @foreach ($project->updates as $u)
        <article class="mx-row mx-between" style="gap:10px;border-top:1px solid var(--mx-border);padding-top:10px;align-items:flex-start">
          <div style="min-width:0"><b>{{ $u->title }}</b> <span class="mx-xs mx-muted">· {{ $u->created_at->format('d/m/Y') }}</span><p class="mx-small" style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($u->body, 240) }}</p></div>
          <form method="POST" action="{{ route('social.manage.update.destroy', [$page->slug, $project->id, $u->id]) }}" data-confirm="Supprimer cette actualité ?">@csrf<button class="mx-iconbtn" aria-label="Supprimer l’actualité"><x-mx.icon name="trash" :size="16"/></button></form>
        </article>
      @endforeach
    </section>

    <form method="POST" action="{{ route('social.manage.project.destroy', [$page->slug, $project->id]) }}" data-confirm="Supprimer ce projet, ses résultats et ses actualités ?" style="padding:0 4px">@csrf
      <button class="mx-btn mx-btn--ghost mx-btn--sm"><x-mx.icon name="trash" :size="14"/> Supprimer le projet</button></form>
  @endif
</x-layouts.social>
