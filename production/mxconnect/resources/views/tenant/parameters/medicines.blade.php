@php
  use App\Models\Tenant\Medicine;
  $canManage = auth()->user()?->can('manage-parameters');
  $badge = [
    'blue' => 'bg-blue-50 text-blue-700 ring-blue-200', 'violet' => 'bg-violet-50 text-violet-700 ring-violet-200',
    'orange' => 'bg-orange-50 text-orange-700 ring-orange-200', 'teal' => 'bg-teal-50 text-teal-700 ring-teal-200',
    'pink' => 'bg-pink-50 text-pink-700 ring-pink-200', 'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    'red' => 'bg-red-50 text-red-700 ring-red-200', 'amber' => 'bg-amber-50 text-amber-700 ring-amber-200',
    'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'cyan' => 'bg-cyan-50 text-cyan-700 ring-cyan-200',
    'slate' => 'bg-slate-100 text-slate-700 ring-slate-200', 'gray' => 'bg-gray-100 text-gray-600 ring-gray-200',
  ];
  $curBadge = fn ($code) => match (true) {
    in_array($code, ['XAF', 'XOF'], true) => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    $code === 'USD' => 'bg-sky-50 text-sky-700 ring-sky-200',
    $code === 'EUR' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    default => 'bg-amber-50 text-amber-700 ring-amber-200',
  };
  $e = $editing;   // médicament en cours de modification (ou null)
  $formCurrency = old('currency_code', $e?->currency_code ?? $currency->code);
  $editCurrency = $e ? $e->currencyIn($currencies, $currency) : null;
  $priceValue = old('reference_price', $e ? (function ($minor, $u) { $v = number_format($minor / (10 ** $u), $u, '.', ''); return $u > 0 ? rtrim(rtrim($v, '0'), '.') : $v; })($e->reference_price_minor, $editCurrency->minor_unit) : '');
  $f = $filters;
  $hasFilters = $f['q'] !== '' || $f['category'] !== '' || $f['supplier'] !== '' || $f['currencyCode'] !== '';
  $ico = fn ($d, $s = 18) => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$d.'</svg>';
  $pill = '<path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7"/><path d="m8.5 8.5 7 7"/>';
@endphp
<x-layouts.tenant :title="__('mxconnect.param.medicines')">
  {{-- En-tête de page --}}
  <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-white via-[#F2F8FF] to-[#DCEBFF] p-6 shadow-soft ring-1 ring-ink/[.05] sm:p-8">
    <div class="relative z-10 flex flex-col gap-5 sm:flex-row sm:items-center">
      <span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-leaf-500 to-leaf-700 text-white shadow-lg shadow-leaf-500/30">{!! $ico($pill, 32) !!}</span>
      <div class="min-w-0">
        <p class="text-xs font-semibold uppercase tracking-[.14em] text-brand-600">Paramètres · Catalogue</p>
        <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-[#0B1F3A] sm:text-[34px]">Médicaments</h1>
        <p class="mt-1 text-[15px] text-ink/60">Gérez le catalogue des médicaments et leurs prix de référence, dans la devise de votre choix.</p>
        @if ($canManage)<a href="{{ route('catalog.import', 'medicaments') }}" class="relative z-10 mt-3 inline-flex h-10 items-center gap-2 rounded-xl border border-ink/10 bg-white px-4 text-sm font-semibold text-[#0B1F3A] hover:bg-paper"><x-tenant.icon name="library" :size="16"/> Liste des médicaments essentiels</a>@endif
      </div>
    </div>
    {{-- Illustration décorative (dessin vectoriel original) --}}
    <svg class="pointer-events-none absolute -right-6 top-1/2 hidden h-[190px] -translate-y-1/2 opacity-90 md:block" viewBox="0 0 420 200" aria-hidden="true">
      <defs>
        <linearGradient id="cap1" x1="0" x2="1"><stop offset=".5" stop-color="#1769D2"/><stop offset=".5" stop-color="#ffffff"/></linearGradient>
        <linearGradient id="cap2" x1="0" x2="1"><stop offset=".5" stop-color="#1FA463"/><stop offset=".5" stop-color="#F5FAFF"/></linearGradient>
        <radialGradient id="glow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#ffffff" stop-opacity=".9"/><stop offset="1" stop-color="#ffffff" stop-opacity="0"/></radialGradient>
        <filter id="sh" x="-20%" y="-20%" width="140%" height="160%"><feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#0B1F3A" flood-opacity=".18"/></filter>
      </defs>
      <circle cx="250" cy="100" r="120" fill="url(#glow)"/>
      <g filter="url(#sh)">
        <rect x="150" y="70" width="120" height="44" rx="22" fill="url(#cap1)" transform="rotate(-24 210 92)"/>
        <rect x="250" y="110" width="104" height="38" rx="19" fill="url(#cap2)" transform="rotate(18 302 129)"/>
        <rect x="300" y="40" width="88" height="32" rx="16" fill="url(#cap1)" transform="rotate(35 344 56)"/>
        <circle cx="200" cy="150" r="22" fill="#fff"/><path d="M183 150h34" stroke="#D9E6F5" stroke-width="3"/>
        <circle cx="380" cy="150" r="18" fill="#fff"/><path d="M366 150h28" stroke="#D9E6F5" stroke-width="3"/>
        <path d="M110 40c0 40 40 60 70 60" fill="none" stroke="#0B4A9C" stroke-width="5" stroke-linecap="round" opacity=".55"/>
        <circle cx="110" cy="36" r="9" fill="none" stroke="#0B4A9C" stroke-width="5" opacity=".55"/>
      </g>
    </svg>
  </section>

  @include('tenant.parameters.partials.tabs')

  {{-- Indicateurs (valeurs réelles du catalogue) --}}
  <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach ([
      ['Médicaments au catalogue', $stats['total'], 'from-brand-500 to-brand-700', $pill],
      ['Catégories utilisées', $stats['categories'], 'from-violet-500 to-violet-700', '<path d="M12 2 2 7l10 5 10-5z"/><path d="m2 17 10 5 10-5M2 12l10 5 10-5"/>'],
      ['Fournisseurs', $stats['suppliers'], 'from-amber-500 to-orange-600', '<path d="M10 17h4V5H2v12h3M14 17h1M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5v8"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>'],
      ['Devises utilisées', $stats['currencies'], 'from-leaf-500 to-leaf-700', '<circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8M12 18V6"/>'],
    ] as [$label, $value, $grad, $path])
      <div class="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-soft ring-1 ring-ink/[.05]">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br {{ $grad }} text-white">{!! $ico($path, 20) !!}</span>
        <div class="min-w-0"><div class="text-2xl font-extrabold leading-none text-[#0B1F3A]">{{ number_format($value, 0, ',', ' ') }}</div><div class="mt-1 text-xs font-medium leading-tight text-ink/50">{{ $label }}</div></div>
      </div>
    @endforeach
  </div>

  <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1fr)_400px] xl:grid-cols-[minmax(0,1fr)_360px]">
    {{-- Liste --}}
    <section class="min-w-0 rounded-3xl bg-white shadow-soft ring-1 ring-ink/[.05]" aria-labelledby="med-list-title">
      <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5 sm:px-6">
        <h2 id="med-list-title" class="flex items-center gap-2.5 text-lg font-bold text-[#0B1F3A]">
          <span class="grid h-8 w-8 place-items-center rounded-full bg-leaf-500 text-white">{!! $ico($pill, 16) !!}</span> Liste des médicaments
        </h2>
        <span class="text-sm text-ink/50">{{ $medicines->total() }} résultat(s)</span>
      </div>

      <form method="GET" action="{{ route('medicines.index') }}" class="grid gap-2.5 px-5 pt-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(0,1fr))_auto]" role="search" aria-label="Filtrer les médicaments">
        <label class="relative block">
          <span class="sr-only">Rechercher</span>
          <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink/40">{!! $ico('<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>', 17) !!}</span>
          <input name="q" value="{{ $f['q'] }}" placeholder="Code, libellé ou fournisseur…" class="h-11 w-full rounded-xl border border-ink/10 bg-paper pl-9 pr-3 text-sm outline-none transition focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-100">
        </label>
        <label class="block"><span class="sr-only">Catégorie</span>
          <select name="categorie" class="h-11 w-full rounded-xl border border-ink/10 bg-white px-3 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
            <option value="">Toutes les catégories</option>
            @foreach (Medicine::CATEGORIES as $k => [$l]) <option value="{{ $k }}" @selected($f['category'] === $k)>{{ $l }}</option> @endforeach
          </select>
        </label>
        <label class="block"><span class="sr-only">Fournisseur</span>
          <select name="fournisseur" class="h-11 w-full rounded-xl border border-ink/10 bg-white px-3 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
            <option value="">Tous les fournisseurs</option>
            @foreach ($suppliers as $s) <option @selected($f['supplier'] === $s)>{{ $s }}</option> @endforeach
          </select>
        </label>
        <label class="block"><span class="sr-only">Devise</span>
          <select name="devise" class="h-11 w-full rounded-xl border border-ink/10 bg-white px-3 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
            <option value="">Toutes les devises</option>
            @foreach ($currencies as $c) <option value="{{ $c->code }}" @selected($f['currencyCode'] === $c->code)>{{ $c->code }} — {{ $c->name }}</option> @endforeach
          </select>
        </label>
        <div class="flex gap-2">
          <button class="grid h-11 flex-1 place-items-center rounded-xl bg-[#0B1F3A] px-4 text-sm font-semibold text-white hover:bg-brand-700 sm:w-11 sm:flex-none sm:px-0" title="Filtrer" aria-label="Filtrer">{!! $ico('<path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>', 18) !!}</button>
          @if ($hasFilters)<a href="{{ route('medicines.index') }}" class="grid h-11 place-items-center rounded-xl border border-ink/10 px-3 text-sm font-medium text-ink/60 hover:bg-paper" title="Effacer les filtres">Effacer</a>@endif
        </div>
      </form>

      @if ($medicines->isEmpty())
        <div class="px-6 py-14 text-center">
          <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-leaf-50 text-leaf-600">{!! $ico($pill, 30) !!}</span>
          <h3 class="mt-4 font-bold text-[#0B1F3A]">{{ $hasFilters ? 'Aucun médicament ne correspond à ces filtres' : 'Le catalogue est vide' }}</h3>
          <p class="mx-auto mt-1 max-w-sm text-sm text-ink/55">{{ $hasFilters ? 'Modifiez la recherche ou effacez les filtres.' : 'Ajoutez votre premier médicament avec le formulaire : code, libellé, catégorie et prix de référence.' }}</p>
        </div>
      @else
        {{-- Tableau (ordinateur) --}}
        <div class="mt-4 hidden overflow-x-auto md:block">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-[#EEF4FC] text-left text-[12.5px] font-semibold text-[#0B1F3A]/80">
                <th class="py-3 pl-6 pr-3">Code</th><th class="px-3 py-3">Libellé</th><th class="px-3 py-3">Catégorie</th>
                <th class="hidden px-3 py-3 min-[1760px]:table-cell">Fournisseur</th><th class="whitespace-nowrap px-3 py-3 text-right">Prix de référence</th>
                <th class="py-3 pl-2 pr-5 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-ink/[.06]">
              @foreach ($medicines as $m)
                @php $cur = $m->currencyIn($currencies, $currency); @endphp
                <tr class="transition hover:bg-[#F7FAFE] {{ $e?->id === $m->id ? 'bg-brand-50/60' : '' }}">
                  <td class="py-3.5 pl-6 pr-3"><span class="inline-flex rounded-lg bg-brand-50 px-2.5 py-1 font-mono text-xs font-semibold text-brand-700 ring-1 ring-brand-100">{{ $m->code }}</span></td>
                  <td class="min-w-[180px] px-3 py-3.5"><div class="font-semibold text-[#0B1F3A]">{{ $m->label }}</div>@if($m->presentation)<div class="text-xs text-ink/50">{{ $m->presentation }}</div>@endif @if($m->supplier)<div class="text-xs text-ink/50 min-[1760px]:hidden">Fournisseur : {{ $m->supplier }}</div>@endif</td>
                  <td class="px-3 py-3.5">@if($m->category)<span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $badge[$m->categoryColor()] }}">{{ $m->categoryLabel() }}</span>@else<span class="text-xs text-ink/35">—</span>@endif</td>
                  <td class="hidden px-3 py-3.5 text-ink/70 min-[1760px]:table-cell">{{ $m->supplier ?: '—' }}</td>
                  <td class="whitespace-nowrap px-3 py-3.5 text-right font-semibold tabular-nums text-[#0B1F3A]">@if($m->reference_price_minor <= 0)<span class="mr-1 rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-700 ring-1 ring-amber-200">prix à renseigner</span>@else{{ number_format($m->reference_price_minor / (10 ** $cur->minor_unit), $cur->minor_unit, ',', ' ') }}@endif <span class="ml-1 inline-flex rounded-md px-1.5 py-0.5 align-middle text-[10.5px] font-bold ring-1 {{ $curBadge($cur->code) }}" title="{{ $cur->name }}">{{ $cur->code === 'XAF' ? 'FCFA' : $cur->code }}</span></td>
                  <td class="py-3.5 pl-2 pr-5">
                    @include('tenant.parameters.partials.medicine-actions', ['m' => $m, 'cur' => $cur])
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        {{-- Cartes (téléphone) --}}
        <ul class="mt-4 divide-y divide-ink/[.06] md:hidden">
          @foreach ($medicines as $m)
            @php $cur = $m->currencyIn($currencies, $currency); @endphp
            <li class="px-5 py-4">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <span class="inline-flex rounded-lg bg-brand-50 px-2 py-0.5 font-mono text-[11px] font-semibold text-brand-700 ring-1 ring-brand-100">{{ $m->code }}</span>
                  <div class="mt-1.5 font-semibold text-[#0B1F3A]">{{ $m->label }}</div>
                  @if($m->presentation)<div class="text-xs text-ink/50">{{ $m->presentation }}</div>@endif
                </div>
                <div class="text-right"><div class="font-bold tabular-nums text-[#0B1F3A]">{{ $cur->format($m->reference_price_minor) }}</div><div class="text-[11px] text-ink/45">{{ $cur->code }}</div></div>
              </div>
              <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                  @if($m->category)<span class="rounded-full px-2.5 py-1 font-semibold ring-1 {{ $badge[$m->categoryColor()] }}">{{ $m->categoryLabel() }}</span>@endif
                  @if($m->supplier)<span class="text-ink/55">{{ $m->supplier }}</span>@endif
                </div>
                @include('tenant.parameters.partials.medicine-actions', ['m' => $m, 'cur' => $cur])
              </div>
            </li>
          @endforeach
        </ul>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-ink/[.06] px-5 py-4 text-sm text-ink/55 sm:px-6">
          <span>Affichage de {{ $medicines->firstItem() }} à {{ $medicines->lastItem() }} sur {{ $medicines->total() }} médicament(s)</span>
          @if ($medicines->hasPages())
            <nav class="flex items-center gap-1" aria-label="Pagination">
              <a href="{{ $medicines->previousPageUrl() ?: '#' }}" @class(['grid h-9 w-9 place-items-center rounded-lg ring-1 ring-ink/10', 'pointer-events-none opacity-40' => $medicines->onFirstPage(), 'hover:bg-paper' => ! $medicines->onFirstPage()]) aria-label="Page précédente">‹</a>
              @foreach ($medicines->getUrlRange(max(1, $medicines->currentPage() - 2), min($medicines->lastPage(), $medicines->currentPage() + 2)) as $p => $u)
                <a href="{{ $u }}" @class(['grid h-9 min-w-9 place-items-center rounded-lg px-2 font-semibold', 'bg-brand-500 text-white' => $p === $medicines->currentPage(), 'ring-1 ring-ink/10 hover:bg-paper' => $p !== $medicines->currentPage()]) @if($p === $medicines->currentPage()) aria-current="page" @endif>{{ $p }}</a>
              @endforeach
              <a href="{{ $medicines->nextPageUrl() ?: '#' }}" @class(['grid h-9 w-9 place-items-center rounded-lg ring-1 ring-ink/10', 'pointer-events-none opacity-40' => ! $medicines->hasMorePages(), 'hover:bg-paper' => $medicines->hasMorePages()]) aria-label="Page suivante">›</a>
            </nav>
          @endif
        </div>
      @endif
    </section>

    {{-- Formulaire --}}
    @if ($canManage)
      <section id="formulaire" class="h-max overflow-hidden rounded-3xl bg-white shadow-soft ring-1 ring-ink/[.05] xl:sticky xl:top-24" aria-labelledby="med-form-title">
        <div class="flex items-center gap-3 bg-gradient-to-r {{ $e ? 'from-brand-500 to-[#0B2A5B]' : 'from-leaf-500 via-leaf-600 to-[#0B4A9C]' }} px-5 py-4 text-white">
          <span class="grid h-9 w-9 place-items-center rounded-full bg-white/20 ring-2 ring-white/40">{!! $ico($e ? '<path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>' : '<path d="M12 5v14M5 12h14"/>', 18) !!}</span>
          <h2 id="med-form-title" class="text-lg font-bold">{{ $e ? 'Modifier le médicament' : 'Ajouter un médicament' }}</h2>
        </div>
        <form method="POST" action="{{ $e ? route('medicines.update', $e) : route('medicines.store') }}" class="space-y-4 p-5" novalidate>
          @csrf
          @if ($e) @method('PUT') @foreach (['q', 'categorie', 'fournisseur', 'devise', 'page'] as $k)@if(request($k) !== null)<input type="hidden" name="{{ $k }}" value="{{ request($k) }}">@endif @endforeach @endif
          @php $field = 'h-11 w-full rounded-xl border bg-white px-3.5 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100'; @endphp
          <div>
            <label for="m-code" class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Code du médicament <span class="text-clay">*</span></label>
            <input id="m-code" name="code" value="{{ old('code', $e?->code) }}" required maxlength="30" placeholder="Ex. MED001" autocomplete="off" class="{{ $field }} font-mono uppercase placeholder:normal-case placeholder:font-sans {{ $errors->has('code') ? 'border-clay' : 'border-ink/12 border-ink/10' }}">
            @error('code')<p class="mt-1 text-xs text-clay">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="m-label" class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Libellé <span class="text-clay">*</span></label>
            <input id="m-label" name="label" value="{{ old('label', $e?->label) }}" required maxlength="180" placeholder="Ex. Paracétamol 500 mg" class="{{ $field }} {{ $errors->has('label') ? 'border-clay' : 'border-ink/10' }}">
            @error('label')<p class="mt-1 text-xs text-clay">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="m-pres" class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Forme et conditionnement <span class="font-normal text-ink/45">(facultatif)</span></label>
            <input id="m-pres" name="presentation" value="{{ old('presentation', $e?->presentation) }}" maxlength="120" placeholder="Ex. Comprimé, boîte de 16" class="{{ $field }} border-ink/10">
          </div>
          <div>
            <label for="m-cat" class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Catégorie <span class="text-clay">*</span></label>
            <select id="m-cat" name="category" required class="{{ $field }} {{ $errors->has('category') ? 'border-clay' : 'border-ink/10' }}">
              <option value="">Sélectionner une catégorie</option>
              @foreach (Medicine::CATEGORIES as $k => [$l]) <option value="{{ $k }}" @selected(old('category', $e?->category) === $k)>{{ $l }}</option> @endforeach
            </select>
            @error('category')<p class="mt-1 text-xs text-clay">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="m-sup" class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Fournisseur <span class="font-normal text-ink/45">(facultatif)</span></label>
            <input id="m-sup" name="supplier" list="m-suppliers" value="{{ old('supplier', $e?->supplier) }}" maxlength="120" placeholder="Choisir ou saisir un fournisseur" class="{{ $field }} border-ink/10">
            <datalist id="m-suppliers">@foreach ($suppliers as $s)<option value="{{ $s }}">@endforeach</datalist>
          </div>
          <div>
            <label for="m-price" class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Prix de référence <span class="text-clay">*</span></label>
            <div class="flex gap-2">
              <input id="m-price" name="reference_price" value="{{ $priceValue }}" required inputmode="decimal" placeholder="Ex. 1 250" class="h-11 min-w-0 flex-1 rounded-xl border bg-white px-3.5 text-sm tabular-nums outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100 {{ $errors->has('reference_price') ? 'border-clay' : 'border-ink/10' }}">
              <label class="sr-only" for="m-cur">Devise</label>
              <select id="m-cur" name="currency_code" class="h-11 w-[124px] shrink-0 rounded-xl border border-ink/10 bg-white px-2.5 text-sm font-semibold outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                @foreach ($currencies as $c) <option value="{{ $c->code }}" @selected($formCurrency === $c->code) title="{{ $c->name }}">{{ $c->code }} · {{ $c->symbol }}</option> @endforeach
              </select>
            </div>
            @error('reference_price')<p class="mt-1 text-xs text-clay">{{ $message }}</p>@enderror
            @error('currency_code')<p class="mt-1 text-xs text-clay">{{ $message }}</p>@enderror
            <p class="mt-1 text-[11.5px] text-ink/45">Devise de la mutuelle : {{ $currency->name }} ({{ $currency->code }}). Le montant est enregistré dans la devise choisie, sans conversion.</p>
          </div>
          <div>
            <label for="m-desc" class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Description <span class="font-normal text-ink/45">(facultatif)</span></label>
            <textarea id="m-desc" name="description" rows="3" maxlength="2000" placeholder="Composition, posologie usuelle, remarques…" class="w-full rounded-xl border border-ink/10 px-3.5 py-2.5 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100">{{ old('description', $e?->description) }}</textarea>
          </div>
          <div class="grid grid-cols-2 gap-2.5 pt-1">
            <button class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 text-sm font-bold text-white shadow-lg shadow-leaf-500/25 transition hover:brightness-105">
              {!! $ico('<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/>', 17) !!} Enregistrer
            </button>
            @if ($e)
              <a href="{{ route('medicines.index', request()->only(['q', 'categorie', 'fournisseur', 'devise', 'page'])) }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-ink/12 border-ink/10 text-sm font-semibold text-ink/70 hover:bg-paper">Annuler</a>
            @else
              <button type="reset" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-ink/10 text-sm font-semibold text-ink/70 hover:bg-paper">
                {!! $ico('<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>', 16) !!} Réinitialiser
              </button>
            @endif
          </div>
        </form>
      </section>
    @endif
  </div>

  {{-- Fiche détaillée (lecture) --}}
  <dialog id="med-view" class="w-[min(520px,calc(100vw-24px))] rounded-3xl p-0 shadow-2xl backdrop:bg-[#011544]/50">
    <div class="flex items-center justify-between bg-gradient-to-r from-[#0B2A5B] to-brand-500 px-6 py-4 text-white">
      <div><div class="text-xs font-semibold uppercase tracking-wider text-white/70" data-v="code"></div><div class="text-lg font-bold" data-v="label"></div></div>
      <form method="dialog"><button class="grid h-9 w-9 place-items-center rounded-full bg-white/15 hover:bg-white/25" aria-label="Fermer">✕</button></form>
    </div>
    <dl class="grid grid-cols-[150px_1fr] gap-x-4 gap-y-3 px-6 py-5 text-sm">
      <dt class="text-ink/50">Forme</dt><dd data-v="presentation"></dd>
      <dt class="text-ink/50">Catégorie</dt><dd data-v="category"></dd>
      <dt class="text-ink/50">Fournisseur</dt><dd data-v="supplier"></dd>
      <dt class="text-ink/50">Prix de référence</dt><dd class="font-bold text-[#0B1F3A]" data-v="price"></dd>
      <dt class="text-ink/50">Devise</dt><dd data-v="currency"></dd>
      <dt class="text-ink/50">Utilisation</dt><dd data-v="usage"></dd>
      <dt class="text-ink/50">Mis à jour</dt><dd data-v="updated"></dd>
      <dt class="text-ink/50">Description</dt><dd class="whitespace-pre-line" data-v="description"></dd>
    </dl>
  </dialog>

  <x-slot:scripts>
    <script>
      document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-med]'); if (!b) return;
        var d = JSON.parse(b.getAttribute('data-med')), dlg = document.getElementById('med-view');
        dlg.querySelectorAll('[data-v]').forEach(function (n) { n.textContent = d[n.getAttribute('data-v')] || '—'; });
        if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', '');
      });
      document.addEventListener('submit', function (e) {
        var f = e.target; if (f.dataset.confirm && !window.confirm(f.dataset.confirm)) e.preventDefault();
      });
      @if ($e) document.getElementById('formulaire')?.scrollIntoView({ block: 'start' }); document.getElementById('m-label')?.focus({ preventScroll: true }); @endif
    </script>
  </x-slot:scripts>
</x-layouts.tenant>
