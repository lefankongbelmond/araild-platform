@php
  $field = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm focus:border-pine focus:outline-none';
  $central = rtrim(config('app.url'), '/');
  $editing = $project->exists;
  $I = \App\Models\Social\ProjectIndicator::class;
@endphp
<x-layouts.tenant :title="$editing ? 'Modifier le projet' : 'Nouveau projet'">
  <a href="{{ route('engagement.index', ['onglet' => 'projets']) }}" class="text-sm text-pine hover:underline">← Projets</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $editing ? $project->title : 'Nouveau projet' }}</h1>
  @if ($editing && $project->status === 'published')<p class="text-sm"><a class="text-pine hover:underline" href="{{ $central }}/projets/{{ $project->slug }}" target="_blank" rel="noopener">Voir la page publique du projet</a></p>@endif
  @if (session('success'))<div class="mt-4 rounded-md border border-pine/30 bg-mist px-4 py-3 text-sm text-pine">{{ session('success') }}</div>@endif
  @if ($project->status === 'hidden')<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">Ce projet a été masqué par la modération du réseau.</div>@endif
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('engagement.projects.update', $project->id) : route('engagement.projects.store') }}" class="mt-6 max-w-3xl space-y-4 rounded-lg border border-ink/10 bg-white p-5">
    @csrf
    <label class="block text-sm font-medium">Titre *<input name="title" required minlength="5" maxlength="180" value="{{ old('title', $project->title) }}" class="{{ $field }}"></label>
    <label class="block text-sm font-medium">Résumé * (300 caractères)<textarea name="summary" rows="2" required minlength="10" maxlength="300" class="{{ $field }}">{{ old('summary', $project->summary) }}</textarea></label>
    <label class="block text-sm font-medium">Description<textarea name="description" rows="6" maxlength="8000" class="{{ $field }}">{{ old('description', $project->description) }}</textarea></label>
    <label class="block text-sm font-medium">Objectifs<textarea name="objectives" rows="3" maxlength="3000" class="{{ $field }}">{{ old('objectives', $project->objectives) }}</textarea></label>
@php $selD = old('domains', $project->domains ?? $mutual->profile?->domains ?? []) ?: []; @endphp
    <fieldset class="text-sm"><legend class="font-medium">Domaines d’intervention</legend>
      <div class="mt-2 flex flex-wrap gap-2">@foreach (\App\Support\SocialDomains::ALL as $code => $label)<label class="flex items-center gap-2 rounded-md border border-ink/10 bg-white px-3 py-1.5"><input type="checkbox" name="domains[]" value="{{ $code }}" @checked(in_array($code, (array) $selD, true))> {{ $label }}</label>@endforeach</div></fieldset>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm font-medium">Avancement déclaré (%)<input type="number" min="0" max="100" name="progress" value="{{ old('progress', $project->progress) }}" class="{{ $field }}"></label>
      <label class="block text-sm font-medium">Bénéficiaires (chiffres globaux, jamais de noms)<input name="beneficiaries" maxlength="255" value="{{ old('beneficiaries', $project->beneficiaries) }}" class="{{ $field }}"></label>
      <label class="block text-sm font-medium">État
        <select name="phase" class="{{ $field }}">@foreach (\App\Models\Social\Project::PHASES as $k => $v)<option value="{{ $k }}" @selected(old('phase', $project->phase) === $k)>{{ $v }}</option>@endforeach</select></label>
      <label class="block text-sm font-medium">Zone d’intervention<input name="zone" maxlength="255" value="{{ old('zone', $project->zone) }}" class="{{ $field }}"></label>
      <label class="block text-sm font-medium">Région<input name="region" maxlength="120" value="{{ old('region', $project->region) }}" class="{{ $field }}"></label>
      <label class="block text-sm font-medium">Ville<input name="city" maxlength="120" value="{{ old('city', $project->city) }}" class="{{ $field }}"></label>
      <label class="block text-sm font-medium">Début<input type="date" name="starts_on" value="{{ old('starts_on', $project->starts_on?->format('Y-m-d')) }}" class="{{ $field }}"></label>
      <label class="block text-sm font-medium">Fin<input type="date" name="ends_on" value="{{ old('ends_on', $project->ends_on?->format('Y-m-d')) }}" class="{{ $field }}"></label>
    </div>
    <div class="space-y-3 rounded-md bg-mist px-3 py-3 text-sm">
      <label class="flex items-start gap-2"><input type="hidden" name="seeks_volunteers" value="0"><input type="checkbox" name="seeks_volunteers" value="1" class="mt-1" @checked(old('seeks_volunteers', $project->seeks_volunteers))> <span>Nous cherchons des bénévoles (bouton « Je m’engage »)</span></label>
      <label class="block font-medium">Ce que les bénévoles pourront faire<textarea name="volunteer_needs" rows="2" maxlength="2000" class="{{ $field }}">{{ old('volunteer_needs', $project->volunteer_needs) }}</textarea></label>
      <label class="flex items-start gap-2"><input type="hidden" name="seeks_partners" value="0"><input type="checkbox" name="seeks_partners" value="1" class="mt-1" @checked(old('seeks_partners', $project->seeks_partners))> <span>Nous cherchons des partenaires (bouton « Soutenir ou devenir partenaire »)</span></label>
      <label class="block font-medium">Soutien recherché<textarea name="partner_needs" rows="2" maxlength="2000" class="{{ $field }}">{{ old('partner_needs', $project->partner_needs) }}</textarea></label>
      <label class="block font-medium">Partenaires actuels (qui ont accepté d’être cités)<textarea name="partners" rows="2" maxlength="2000" class="{{ $field }}">{{ old('partners', $project->partners) }}</textarea></label>
    </div>
    <div class="text-sm">
      <div class="font-medium">Photo de couverture (convertie en WebP, métadonnées supprimées)</div>
      @if ($project->cover_path)<label class="mt-2 flex items-center gap-2 text-xs"><img src="{{ $central . $project->coverUrl() }}" alt="" class="h-20 w-20 rounded object-cover"><input type="checkbox" name="remove_cover" value="1"> Retirer</label>@endif
      <input type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif" class="mt-2 block text-xs">
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm font-medium">Statut
        <select name="status" class="{{ $field }}"><option value="published" @selected(old('status', $project->status === 'draft' ? 'draft' : 'published') === 'published')>Publié</option><option value="draft" @selected(old('status', $project->status) === 'draft')>Brouillon</option></select></label>
      @unless ($project->published_at)
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="announce" value="1" class="mt-1" @checked(old('announce', true))> <span>Annoncer le projet dans le fil du réseau</span></label>
      @endunless
    </div>
    <label class="flex items-start gap-2 rounded-md bg-mist px-3 py-2 text-sm"><input type="checkbox" name="medical_ack" value="1" class="mt-1" required> <span>Je confirme que ce projet ne contient aucune donnée médicale ou personnelle d’adhérent.</span></label>
    <div class="flex gap-3">
      <button class="rounded-md bg-pine px-5 py-2 text-sm font-medium text-white hover:bg-navy">Enregistrer</button>
      <a href="{{ route('engagement.index', ['onglet' => 'projets']) }}" class="px-3 py-2 text-sm text-ink/60 hover:underline">Annuler</a>
    </div>
  </form>

  @if ($editing)
    <section id="resultats" class="mt-6 max-w-3xl space-y-4 rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="font-semibold">Résultats chiffrés</h2>
      <p class="text-xs text-ink/45">Chiffres réels uniquement, avec leur source. Ils s’affichent « Déclaré » ; seule l’administration du réseau peut les marquer « Vérifié ». Modifier un chiffre vérifié annule sa vérification.</p>
      @if ($errors->indicator->any())<div class="rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-clay">{{ $errors->indicator->first() }}</div>@endif
      @foreach ($project->indicators as $i)
        <form method="POST" action="{{ route('engagement.indicators.update', [$project->id, $i->id]) }}" class="grid gap-2 border-t border-ink/10 pt-3 sm:grid-cols-2">@csrf
          @include('tenant.engagement._indicator-fields', ['i' => $i])
          <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
            <span class="rounded-full bg-mist px-2 py-0.5 text-xs text-pine">{{ $i->isVerified() ? 'Vérifié le ' . $i->verified_at->format('d/m/Y') : 'Déclaré' }}</span>
            <button class="text-pine hover:underline">Enregistrer</button>
            <button form="del-ind-{{ $i->id }}" class="text-clay hover:underline">Supprimer</button>
          </div>
        </form>
        <form id="del-ind-{{ $i->id }}" method="POST" action="{{ route('engagement.indicators.destroy', [$project->id, $i->id]) }}" onsubmit="return confirm('Supprimer ce résultat ?')">@csrf</form>
      @endforeach
      <form method="POST" action="{{ route('engagement.indicators.store', $project->id) }}" class="grid gap-2 border-t border-ink/10 pt-3 sm:grid-cols-2">@csrf
        <div class="font-medium sm:col-span-2">Ajouter un résultat</div>
        @include('tenant.engagement._indicator-fields', ['i' => new \App\Models\Social\ProjectIndicator()])
        <div class="sm:col-span-2"><button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-navy">Ajouter</button></div>
      </form>
    </section>

    <section id="documents" class="mt-6 max-w-3xl space-y-3 rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="font-semibold">Documents du projet (PDF publics)</h2>
      <p class="text-xs text-ink/45">Rapports, comptes rendus, affiches : publics, ils aident le réseau à vérifier vos résultats. {{ round(\App\Services\Social\DocumentService::maxKb() / 1024) }} Mo chacun, 10 par projet.</p>
      @if ($errors->docs->any())<div class="rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-clay">{{ $errors->docs->first() }}</div>@endif
      <ul class="divide-y divide-ink/5">
        @foreach ($project->documents as $d)
          <li class="flex flex-wrap items-center justify-between gap-3 py-2"><span>{{ $d->title }} <span class="text-xs text-ink/45">({{ $d->sizeLabel() }} · {{ $d->downloads_count }} téléchargement(s))</span></span>
            <form method="POST" action="{{ route('engagement.documents.destroy', [$project->id, $d->id]) }}" onsubmit="return confirm('Supprimer ce document ?')">@csrf<button class="text-clay hover:underline">Supprimer</button></form></li>
        @endforeach
      </ul>
      <form method="POST" action="{{ route('engagement.documents.store', $project->id) }}" enctype="multipart/form-data" class="space-y-3">@csrf
        <label class="block font-medium">Titre (facultatif)<input name="document_titles[0]" maxlength="180" class="{{ $field }}"></label>
        <input type="file" name="documents[]" accept="application/pdf" required class="block text-xs">
        <label class="flex items-start gap-2"><input type="checkbox" name="documents_public" value="1" class="mt-1" required> <span>Ce document est public et ne contient aucune donnée médicale ou personnelle d’adhérent.</span></label>
        <button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-navy">Ajouter le document</button>
      </form>
    </section>

    <section id="actualites" class="mt-6 max-w-3xl space-y-3 rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="font-semibold">Actualités d’avancement</h2>
      @if ($errors->progress->any())<div class="rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-clay">{{ $errors->progress->first() }}</div>@endif
      <form method="POST" action="{{ route('engagement.updates.store', $project->id) }}" class="space-y-3">@csrf
        <label class="block font-medium">Titre<input name="update_title" required maxlength="180" value="{{ old('update_title') }}" class="{{ $field }}"></label>
        <label class="block font-medium">Texte<textarea name="update_body" rows="3" required maxlength="5000" class="{{ $field }}">{{ old('update_body') }}</textarea></label>
        <label class="flex items-start gap-2"><input type="checkbox" name="announce" value="1" class="mt-1" @checked($project->status === 'published')> <span>Publier aussi dans le fil du réseau</span></label>
        <button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-navy">Publier l’actualité</button>
      </form>
      <ul class="divide-y divide-ink/5">
        @foreach ($project->updates as $u)<li class="py-2"><b>{{ $u->title }}</b> <span class="text-xs text-ink/50">· {{ $u->created_at->format('d/m/Y') }}</span><div class="text-ink/70">{{ \Illuminate\Support\Str::limit($u->body, 200) }}</div></li>@endforeach
      </ul>
    </section>

    <form method="POST" action="{{ route('engagement.projects.destroy', $project->id) }}" class="mt-6" onsubmit="return confirm('Supprimer ce projet, ses résultats et ses actualités ?')">@csrf
      <button class="text-sm text-clay hover:underline">Supprimer le projet</button></form>
  @endif
</x-layouts.tenant>
