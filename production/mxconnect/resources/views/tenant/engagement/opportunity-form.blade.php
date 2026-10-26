@php
  $field = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm focus:border-pine focus:outline-none';
  $central = rtrim(config('app.url'), '/');
  $editing = $o->exists;
  $O = \App\Models\Social\Opportunity::class;
@endphp
<x-layouts.tenant :title="$editing ? 'Modifier l’offre' : 'Nouvelle offre'">
  <a href="{{ route('engagement.index', ['onglet' => 'opportunites']) }}" class="text-sm text-pine hover:underline">← Emplois et opportunités</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $editing ? $o->title : 'Nouvelle offre ou opportunité' }}</h1>
  @if ($editing && $o->status === 'published')<p class="text-sm"><a class="text-pine hover:underline" href="{{ $central }}/opportunites/{{ $o->slug }}" target="_blank" rel="noopener">Voir l’offre sur le réseau</a></p>@endif
  @if ($o->status === 'hidden')<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">Cette offre a été masquée par la modération du réseau.</div>@endif
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="POST" action="{{ $editing ? route('engagement.opportunities.update', $o->id) : route('engagement.opportunities.store') }}" class="mt-6 max-w-3xl space-y-4 rounded-lg border border-ink/10 bg-white p-5">
    @csrf
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm font-medium">Type *
        <select name="category" class="{{ $field }}">@foreach ($O::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('category', $o->category) === $k)>{{ $v }}</option>@endforeach</select></label>
      <label class="block text-sm font-medium">Contrat
        <select name="contract_type" class="{{ $field }}"><option value="">—</option>@foreach ($O::CONTRACTS as $k => $v)<option value="{{ $k }}" @selected(old('contract_type', $o->contract_type) === $k)>{{ $v }}</option>@endforeach</select></label>
    </div>
    <label class="block text-sm font-medium">Intitulé *<input name="title" required minlength="5" maxlength="180" value="{{ old('title', $o->title) }}" class="{{ $field }}"></label>
    <label class="block text-sm font-medium">Description *<textarea name="description" rows="7" required minlength="20" maxlength="8000" class="{{ $field }}">{{ old('description', $o->description) }}</textarea></label>
    <label class="block text-sm font-medium">Profil recherché<textarea name="requirements" rows="3" maxlength="4000" class="{{ $field }}">{{ old('requirements', $o->requirements) }}</textarea></label>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm font-medium">Lieu de travail
        <select name="workplace" class="{{ $field }}"><option value="">—</option>@foreach ($O::WORKPLACES as $k => $v)<option value="{{ $k }}" @selected(old('workplace', $o->workplace) === $k)>{{ $v }}</option>@endforeach</select></label>
      <label class="block text-sm font-medium">Pays
        <select name="country_id" class="{{ $field }}"><option value="">—</option>@foreach ($countries as $c)<option value="{{ $c->id }}" @selected((int) old('country_id', $o->country_id) === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
      <label class="block text-sm font-medium">Région<input name="region" maxlength="120" value="{{ old('region', $o->region) }}" class="{{ $field }}"></label>
      <label class="block text-sm font-medium">Ville<input name="city" maxlength="120" value="{{ old('city', $o->city) }}" class="{{ $field }}"></label>
      <label class="block text-sm font-medium">Rémunération (texte libre, votre devise)<input name="remuneration" maxlength="120" value="{{ old('remuneration', $o->remuneration) }}" class="{{ $field }}"></label>
      <label class="block text-sm font-medium">Nombre de postes<input type="number" min="1" max="5000" name="positions" value="{{ old('positions', $o->positions) }}" class="{{ $field }}"></label>
      <label class="block text-sm font-medium">Date limite<input type="date" name="deadline_on" value="{{ old('deadline_on', $o->deadline_on?->format('Y-m-d')) }}" class="{{ $field }}"></label>
      @if ($pageProjects->isNotEmpty())
        <label class="block text-sm font-medium">Projet lié
          <select name="project_id" class="{{ $field }}"><option value="">Aucun</option>@foreach ($pageProjects as $id => $t)<option value="{{ $id }}" @selected((int) old('project_id', $o->project_id) === $id)>{{ \Illuminate\Support\Str::limit($t, 60) }}</option>@endforeach</select></label>
      @endif
    </div>
    <div class="space-y-2 rounded-md bg-mist px-3 py-3 text-sm">
      <div class="font-medium">Candidatures</div>
      @foreach ($O::APPLY_MODES as $k => $v)<label class="flex items-start gap-2"><input type="radio" name="apply_mode" value="{{ $k }}" class="mt-1" @checked(old('apply_mode', $o->apply_mode) === $k)> <span>{{ $v }}</span></label>@endforeach
      <label class="block font-medium">Adresse du site de candidature<input type="url" name="apply_url" maxlength="255" value="{{ old('apply_url', $o->apply_url) }}" class="{{ $field }}" placeholder="https://"></label>
      <label class="block font-medium">Consignes<input name="apply_instructions" maxlength="500" value="{{ old('apply_instructions', $o->apply_instructions) }}" class="{{ $field }}"></label>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm font-medium">Statut
        <select name="status" class="{{ $field }}"><option value="published" @selected(old('status', $o->status === 'draft' ? 'draft' : 'published') === 'published')>Publiée</option><option value="draft" @selected(old('status', $o->status) === 'draft')>Brouillon</option></select></label>
      @unless ($o->published_at)
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="announce" value="1" class="mt-1" @checked(old('announce', true))> <span>Annoncer l’offre dans le fil du réseau</span></label>
      @endunless
    </div>
    <p class="text-xs text-ink/45">Ne demandez jamais d’argent aux candidats. Les candidatures reçues (CV) sont visibles par l’administrateur de la mutuelle uniquement.</p>
    <div class="flex gap-3">
      <button class="rounded-md bg-pine px-5 py-2 text-sm font-medium text-white hover:bg-navy">Enregistrer</button>
      <a href="{{ route('engagement.index', ['onglet' => 'opportunites']) }}" class="px-3 py-2 text-sm text-ink/60 hover:underline">Annuler</a>
    </div>
  </form>
  @if ($editing)
    <form method="POST" action="{{ route('engagement.opportunities.destroy', $o->id) }}" class="mt-6" onsubmit="return confirm('Supprimer cette offre ?')">@csrf
      <button class="text-sm text-clay hover:underline">Supprimer l’offre</button></form>
  @endif
</x-layouts.tenant>
