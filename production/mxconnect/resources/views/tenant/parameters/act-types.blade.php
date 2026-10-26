@php
  $canManage = auth()->user()?->can('manage-parameters');
  $e = $editing;
  $field = 'h-11 w-full rounded-xl border border-ink/10 bg-white px-3.5 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100';
  $tariffValue = old('tariff', $e && $e->tariff_minor !== null ? number_format($e->tariff_minor / (10 ** $currency->minor_unit), $currency->minor_unit, '.', '') : '');
@endphp
<x-layouts.tenant :title="__('mxconnect.param.act_types')">
  <x-tenant.hero icon="stethoscope" kicker="Paramètres · Catalogue" tone="teal">
    <x-slot:title>Actes de soins</x-slot:title>
    <x-slot:subtitle>Nomenclature des actes pris en charge et tarifs conventionnels avec les formations sanitaires. Les plafonds se règlent dans chaque garantie.</x-slot:subtitle>
    @if ($canManage)
      <x-slot:actions><a href="{{ route('catalog.import', 'actes') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-ink/10 bg-white px-4 text-sm font-semibold text-[#0B1F3A] hover:bg-paper"><x-tenant.icon name="library" :size="16"/> Liste de référence</a></x-slot:actions>
    @endif
  </x-tenant.hero>
  @include('tenant.parameters.partials.tabs')

  <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
    <x-tenant.panel :title="'Liste des actes (' . $total . ')'" icon="list">
      <form method="GET" class="grid gap-2.5 px-5 pt-4 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_auto] sm:px-6" role="search">
        <input name="q" value="{{ $filters['q'] }}" placeholder="Code ou libellé…" class="{{ $field }} bg-paper" aria-label="Rechercher">
        <select name="categorie" class="{{ $field }}" aria-label="Catégorie"><option value="">Toutes les catégories</option>@foreach ($categories as $c)<option @selected($filters['cat'] === $c)>{{ $c }}</option>@endforeach</select>
        <button class="h-11 rounded-xl bg-[#0B1F3A] px-5 text-sm font-semibold text-white">Filtrer</button>
      </form>
      @if ($actTypes->isEmpty())
        <div class="px-6 py-14 text-center">
          <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-teal-50 text-teal-600"><x-tenant.icon name="stethoscope" :size="30"/></span>
          <h3 class="mt-4 font-bold text-[#0B1F3A]">Aucun acte</h3>
          <p class="mx-auto mt-1 max-w-sm text-sm text-ink/55">Ajoutez vos actes ou importez la nomenclature de référence (consultations, maternité, laboratoire, imagerie, hospitalisation…).</p>
          @if ($canManage)<a href="{{ route('catalog.import', 'actes') }}" class="mt-4 inline-flex h-10 items-center gap-2 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-4 text-sm font-bold text-white">Importer la nomenclature</a>@endif
        </div>
      @else
        <div class="mt-4 overflow-x-auto">
          <table class="w-full text-sm">
            <thead><tr class="bg-[#EEF4FC] text-left text-[12.5px] font-semibold text-[#0B1F3A]/80"><th class="py-3 pl-6 pr-3">Code</th><th class="px-3 py-3">Libellé</th><th class="px-3 py-3">Catégorie</th><th class="px-3 py-3 text-right">Tarif conventionnel</th><th class="py-3 pl-3 pr-6"></th></tr></thead>
            <tbody class="divide-y divide-ink/[.06]">
              @foreach ($actTypes as $a)
                <tr class="{{ $e?->id === $a->id ? 'bg-brand-50/60' : '' }}">
                  <td class="py-3 pl-6 pr-3"><span class="inline-flex rounded-lg bg-brand-50 px-2.5 py-1 font-mono text-xs font-semibold text-brand-700 ring-1 ring-brand-100">{{ $a->code }}</span></td>
                  <td class="px-3 py-3 font-semibold text-[#0B1F3A]">{{ $a->label }}</td>
                  <td class="px-3 py-3">@if($a->category)<span class="whitespace-nowrap rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 ring-1 ring-teal-200">{{ $a->category }}</span>@endif</td>
                  <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{!! $a->tariff_minor !== null ? '<b class="text-[#0B1F3A]">' . e($currency->format($a->tariff_minor)) . '</b>' : '<span class="text-xs text-ink/40">non fixé</span>' !!}</td>
                  <td class="py-3 pl-3 pr-6 text-right">@if($canManage)<a href="{{ route('act-types.index', array_merge(request()->only(['q', 'categorie']), ['modifier' => $a->id])) }}#formulaire" class="inline-grid h-9 w-9 place-items-center rounded-lg text-[#0B1F3A]/70 hover:bg-paper" title="Modifier" aria-label="Modifier {{ $a->label }}"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>@endif</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </x-tenant.panel>

    @if ($canManage)
      <section id="formulaire" class="h-max overflow-hidden rounded-3xl bg-white shadow-soft ring-1 ring-ink/[.05] xl:sticky xl:top-24">
        <div class="flex items-center gap-3 bg-gradient-to-r {{ $e ? 'from-brand-500 to-[#0B2A5B]' : 'from-leaf-500 via-leaf-600 to-[#0B4A9C]' }} px-5 py-4 text-white">
          <span class="grid h-9 w-9 place-items-center rounded-full bg-white/20 ring-2 ring-white/40"><x-tenant.icon :name="$e ? 'clipboard' : 'plus'" :size="18"/></span>
          <h2 class="text-lg font-bold text-white">{{ $e ? 'Modifier l’acte' : 'Ajouter un acte' }}</h2>
        </div>
        <form method="POST" action="{{ $e ? route('act-types.update', $e) : route('act-types.store') }}" class="space-y-4 p-5">
          @csrf @if ($e) @method('PUT') @endif
          <label class="block"><span class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Code <span class="text-clay">*</span></span><input name="code" value="{{ old('code', $e?->code) }}" required maxlength="30" placeholder="Ex. CS-GEN" class="{{ $field }} font-mono uppercase placeholder:font-sans placeholder:normal-case">@error('code')<span class="mt-1 block text-xs text-clay">{{ $message }}</span>@enderror</label>
          <label class="block"><span class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Libellé <span class="text-clay">*</span></span><input name="label" value="{{ old('label', $e?->label) }}" required maxlength="180" placeholder="Ex. Consultation de médecine générale" class="{{ $field }}"></label>
          <label class="block"><span class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Catégorie</span><input name="category" list="act-cats" value="{{ old('category', $e?->category) }}" maxlength="80" placeholder="Ex. Consultations" class="{{ $field }}"><datalist id="act-cats">@foreach ($categories as $c)<option value="{{ $c }}">@endforeach</datalist></label>
          <label class="block"><span class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Tarif conventionnel ({{ $currency->code }}) <span class="font-normal text-ink/45">(facultatif)</span></span><input name="tariff" value="{{ $tariffValue }}" inputmode="decimal" placeholder="Ex. 2 000" class="{{ $field }} tabular-nums">@error('tariff')<span class="mt-1 block text-xs text-clay">{{ $message }}</span>@enderror
            <span class="mt-1 block text-[11.5px] text-ink/45">Proposé automatiquement dans le calcul de quote-part.</span></label>
          <div class="grid grid-cols-2 gap-2.5 pt-1">
            <button class="h-11 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 text-sm font-bold text-white shadow-lg shadow-leaf-500/25">Enregistrer</button>
            @if ($e)<a href="{{ route('act-types.index') }}" class="grid h-11 place-items-center rounded-xl border border-ink/10 text-sm font-semibold text-ink/70 hover:bg-paper">Annuler</a>@else<button type="reset" class="h-11 rounded-xl border border-ink/10 text-sm font-semibold text-ink/70 hover:bg-paper">Réinitialiser</button>@endif
          </div>
        </form>
      </section>
    @endif
  </div>
</x-layouts.tenant>
