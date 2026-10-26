@php
  $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm focus:border-pine focus:outline-none';
  $editing = $convention->exists;
  $T = \App\Models\Tenant\ProviderConvention::TYPES;
  $own = $provider && $provider->created_by_mutual === (string) tenant('id');
  $mode = old('mode', $available->isNotEmpty() ? 'existing' : 'new');
@endphp
<x-layouts.tenant :title="$editing ? 'Convention · ' . ($provider?->name ?? '') : 'Ajouter un prestataire conventionné'">
  <a href="{{ $editing ? route('providers.show', $convention->id) : route('providers.index') }}" class="text-sm text-pine hover:underline">← {{ $editing ? ($provider?->name ?? 'Prestataire') : 'Prestataires conventionnés' }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $editing ? 'Modifier la convention' : 'Ajouter un prestataire conventionné' }}</h1>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="POST" action="{{ $editing ? route('providers.update', $convention->id) : route('providers.store') }}" class="mt-6 max-w-3xl space-y-5 rounded-lg border border-ink/10 bg-white p-5">
    @csrf
    @unless ($editing)
      <fieldset class="space-y-3 text-sm">
        <legend class="font-semibold">Le prestataire</legend>
        <label class="flex items-start gap-3 rounded-md border border-ink/10 px-3 py-2"><input type="radio" name="mode" value="existing" class="mt-1" @checked($mode === 'existing') @disabled($available->isEmpty())>
          <span class="flex-1"><b>Choisir dans le registre du réseau</b><span class="block text-xs text-ink/55">Établissements déjà enregistrés par MX-CONNECT ou par d’autres mutuelles ({{ $available->count() }} disponible(s)).</span>
            <select name="care_provider_id" class="{{ $f }}"><option value="">—</option>@foreach ($available as $a)<option value="{{ $a->id }}" @selected((int) old('care_provider_id') === $a->id)>{{ $a->name }} · {{ $T[$a->type] ?? $a->type }}@if($a->locality) · {{ $a->locality }}@endif</option>@endforeach</select></span></label>
        <label class="flex items-start gap-3 rounded-md border border-ink/10 px-3 py-2"><input type="radio" name="mode" value="new" class="mt-1" @checked($mode === 'new')>
          <span class="flex-1"><b>Créer un nouvel établissement</b><span class="block text-xs text-ink/55">Il rejoint le registre du réseau ; seule votre mutuelle pourra modifier sa fiche.</span></span></label>
      </fieldset>
    @endunless

    @if (! $editing || $own)
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-medium">Nom de l’établissement @unless($editing)<span class="font-normal text-ink/50">(nouvel établissement)</span>@endunless<input name="name" maxlength="180" value="{{ old('name', $provider?->name) }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">Type<select name="type" class="{{ $f }}">@foreach ($T as $k => $v)<option value="{{ $k }}" @selected(old('type', $provider?->type ?? 'health_center') === $k)>{{ $v }}</option>@endforeach</select></label>
        <label class="block text-sm font-medium">Localité<input name="locality" maxlength="120" value="{{ old('locality', $provider?->locality) }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">Téléphone<input name="phone" maxlength="30" value="{{ old('phone', $provider?->phone) }}" class="{{ $f }}"></label>
      </div>
    @else
      <p class="rounded-md bg-mist px-3 py-2 text-sm"><b>{{ $provider?->name }}</b> · {{ $T[$provider?->type] ?? '' }}@if($provider?->locality) · {{ $provider->locality }}@endif<span class="block text-xs text-ink/55">Fiche du registre du réseau, gérée par une autre structure : seule la convention se modifie ici.</span></p>
    @endif

    <fieldset class="grid gap-4 sm:grid-cols-2">
      <legend class="mb-2 font-semibold text-sm">La convention</legend>
      <label class="block text-sm font-medium">N° de convention<input name="reference" maxlength="60" value="{{ old('reference', $convention->reference) }}" class="{{ $f }}"></label>
      <div></div>
      <label class="block text-sm font-medium">Début<input type="date" name="starts_on" value="{{ old('starts_on', $convention->starts_on?->toDateString()) }}" class="{{ $f }}"></label>
      <label class="block text-sm font-medium">Fin<input type="date" name="ends_on" value="{{ old('ends_on', $convention->ends_on?->toDateString()) }}" class="{{ $f }}"></label>
      <label class="block text-sm font-medium">Contact<input name="contact_name" maxlength="120" value="{{ old('contact_name', $convention->contact_name) }}" class="{{ $f }}"></label>
      <label class="block text-sm font-medium">Téléphone du contact<input name="contact_phone" maxlength="30" value="{{ old('contact_phone', $convention->contact_phone) }}" class="{{ $f }}"></label>
      <label class="block text-sm font-medium sm:col-span-2">Notes (tarifs convenus, conditions…)<textarea name="notes" rows="3" maxlength="2000" class="{{ $f }}">{{ old('notes', $convention->notes) }}</textarea></label>
    </fieldset>
    <div class="flex gap-3">
      <button class="rounded-md bg-pine px-5 py-2 text-sm font-medium text-white hover:bg-navy">{{ $editing ? 'Enregistrer' : 'Ajouter le prestataire' }}</button>
      <a href="{{ route('providers.index') }}" class="px-3 py-2 text-sm text-ink/60 hover:underline">Annuler</a>
    </div>
  </form>
</x-layouts.tenant>
