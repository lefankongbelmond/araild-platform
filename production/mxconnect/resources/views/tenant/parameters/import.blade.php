@php
  use App\Models\Tenant\Medicine;
  $titles = ['actes' => 'Nomenclature des actes', 'medicaments' => 'Liste des médicaments essentiels', 'garanties' => 'Formules de garanties types'];
  $icons = ['actes' => 'stethoscope', 'medicaments' => 'pill', 'garanties' => 'heart'];
  $back = ['actes' => 'act-types.index', 'medicaments' => 'medicines.index', 'garanties' => 'guarantees.index'][$kind];
  $available = collect($items)->keys()->reject(fn ($c) => isset($existing[$c]))->count();
  $f = 'h-10 rounded-xl border border-ink/10 bg-white px-3 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100';
@endphp
<x-layouts.tenant :title="$titles[$kind]">
  <a href="{{ route($back) }}" class="text-sm text-ink/50 hover:underline">&larr; Retour</a>
  <x-tenant.hero :icon="$icons[$kind]" kicker="Paramètres · Listes de référence" tone="teal" class="mt-2">
    <x-slot:title>{{ $titles[$kind] }}</x-slot:title>
    <x-slot:subtitle>Modèle inspiré des pratiques courantes des mutuelles de santé d’Afrique de l’Ouest et d’Afrique centrale. Cochez ce que votre mutuelle utilise : les éléments déjà présents (même code) ne sont jamais modifiés.</x-slot:subtitle>
  </x-tenant.hero>

  <nav class="mt-5 flex gap-1 overflow-x-auto rounded-2xl bg-white p-1.5 shadow-soft ring-1 ring-ink/[.05]" aria-label="Listes de référence">
    @foreach ($kinds as $k => $l)
      <a href="{{ route('catalog.import', $k) }}" @class(['whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold', 'bg-leaf-50 text-leaf-700 ring-1 ring-leaf-100' => $k === $kind, 'text-ink/60 hover:bg-paper' => $k !== $kind])>{{ $l }}</a>
    @endforeach
  </nav>

  <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    @if ($kind === 'garanties')
      <b>Valeurs à valider par votre assemblée générale.</b> Les taux (70 à 80 % de prise en charge, ticket modérateur de 20 à 30 %) et les stages d’attente sont des valeurs usuelles, modifiables ci-dessous. La cotisation est propre à chaque mutuelle : elle doit être saisie, en {{ $currency->name }} ({{ $currency->code }}). Les plafonds se définissent ensuite dans chaque garantie.
    @elseif ($kind === 'medicaments')
      <b>Aucun prix n’est importé.</b> Les prix varient selon le pays et le fournisseur : chaque médicament importé est marqué « prix à renseigner » dans le catalogue.
    @else
      <b>Aucun tarif n’est importé.</b> Les tarifs dépendent des conventions signées avec les formations sanitaires : vous pouvez les saisir ensuite dans le catalogue des actes.
    @endif
  </div>

  @error('g')<div class="mt-4 rounded-xl border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $message }}</div>@enderror

  <form method="POST" action="{{ route('catalog.import.store', $kind) }}" class="mt-5">
    @csrf
    <x-tenant.panel :title="count($items) . ' éléments proposés · ' . $available . ' à importer'" icon="library">
      <x-slot:aside>
        <label class="inline-flex cursor-pointer items-center gap-2 font-medium text-ink/70"><input type="checkbox" data-all checked> Tout cocher</label>
        <input type="search" data-filter placeholder="Filtrer…" class="h-10 w-44 rounded-xl border border-ink/10 bg-paper px-3 text-sm">
      </x-slot:aside>
      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="bg-[#EEF4FC] text-left text-[12.5px] font-semibold text-[#0B1F3A]/80">
            <th class="w-10 py-3 pl-6"></th><th class="px-3 py-3">Code</th><th class="px-3 py-3">Libellé</th>
            @if ($kind === 'actes')<th class="px-3 py-3">Catégorie</th>
            @elseif ($kind === 'medicaments')<th class="px-3 py-3">Forme</th><th class="px-3 py-3">Catégorie</th>
            @else<th class="px-3 py-3">Prise en charge</th><th class="px-3 py-3">Stage (jours)</th><th class="px-3 py-3">Périodicité</th><th class="px-3 py-3 pr-6">Cotisation ({{ $currency->code }}) *</th>@endif
          </tr></thead>
          <tbody class="divide-y divide-ink/[.06]">
            @foreach ($items as $code => $it)
              @php $has = isset($existing[$code]); @endphp
              <tr data-row="{{ mb_strtolower($code . ' ' . $it[0] . ' ' . ($it[1] ?? '')) }}" @class(['opacity-50' => $has])>
                <td class="py-3 pl-6"><input type="checkbox" name="codes[]" value="{{ $code }}" @checked(! $has && (old('codes') === null || in_array($code, old('codes', []), true))) @disabled($has) data-item aria-label="Importer {{ $it[0] }}"></td>
                <td class="px-3 py-3"><span class="inline-flex whitespace-nowrap rounded-lg bg-brand-50 px-2 py-0.5 font-mono text-[11px] font-semibold text-brand-700 ring-1 ring-brand-100">{{ $code }}</span>@if($has)<div class="mt-1 text-[11px] text-ink/45">Déjà présent</div>@endif</td>
                @if ($kind === 'actes')
                  <td class="px-3 py-3 font-medium text-[#0B1F3A]">{{ $it[0] }}</td><td class="px-3 py-3 text-ink/60">{{ $it[1] }}</td>
                @elseif ($kind === 'medicaments')
                  <td class="px-3 py-3 font-medium text-[#0B1F3A]">{{ $it[0] }}</td><td class="px-3 py-3 text-ink/60">{{ $it[1] }}</td>
                  <td class="px-3 py-3"><span class="rounded-full bg-paper px-2.5 py-1 text-xs font-semibold text-ink/70 ring-1 ring-ink/10">{{ Medicine::CATEGORIES[$it[2]][0] ?? $it[2] }}</span></td>
                @else
                  @php [$name, $desc, $cov, $cop, $obs, $per] = $it; $o = old("g.$code", []); @endphp
                  <td class="max-w-sm px-3 py-3"><div class="font-semibold text-[#0B1F3A]">{{ $name }}</div><div class="text-xs text-ink/55">{{ $desc }}</div></td>
                  <td class="px-3 py-3"><div class="flex items-center gap-1"><input name="g[{{ $code }}][coverage]" value="{{ $o['coverage'] ?? $cov }}" inputmode="decimal" class="{{ $f }} w-20" aria-label="Taux de prise en charge"> <span class="text-xs text-ink/50">%</span></div><div class="mt-1 text-[11px] text-ink/45">ticket modérateur = le reste</div></td>
                  <td class="px-3 py-3"><input name="g[{{ $code }}][observation]" value="{{ $o['observation'] ?? $obs }}" inputmode="numeric" class="{{ $f }} w-20" aria-label="Stage d’attente en jours"></td>
                  <td class="px-3 py-3"><select name="g[{{ $code }}][periodicity]" class="{{ $f }} w-36" aria-label="Périodicité">@foreach (['monthly' => 'Mensuelle', 'quarterly' => 'Trimestrielle', 'annual' => 'Annuelle'] as $pk => $pl)<option value="{{ $pk }}" @selected(($o['periodicity'] ?? $per) === $pk)>{{ $pl }}</option>@endforeach</select></td>
                  <td class="px-3 py-3 pr-6"><input name="g[{{ $code }}][contribution]" value="{{ $o['contribution'] ?? '' }}" inputmode="decimal" placeholder="à saisir" class="{{ $f }} w-32" aria-label="Montant de la cotisation">
                    <input name="g[{{ $code }}][fee]" value="{{ $o['fee'] ?? '' }}" inputmode="decimal" placeholder="droit d’adhésion (facultatif)" class="{{ $f }} mt-1.5 w-32 text-xs" aria-label="Droit d’adhésion"></td>
                @endif
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-ink/[.06] px-6 py-4">
        <span class="text-sm text-ink/55" data-count></span>
        <button class="inline-flex h-11 items-center gap-2 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-5 text-sm font-bold text-white shadow-lg shadow-leaf-500/25"><x-tenant.icon name="download" :size="17"/> Importer la sélection</button>
      </div>
    </x-tenant.panel>
  </form>

  <x-slot:scripts>
    <script>
      (function () {
        var items = function () { return Array.prototype.slice.call(document.querySelectorAll('[data-item]:not(:disabled)')); };
        var count = function () { var n = items().filter(function (i) { return i.checked; }).length; document.querySelector('[data-count]').textContent = n + ' élément(s) sélectionné(s)'; };
        document.querySelector('[data-all]').addEventListener('change', function (e) { items().forEach(function (i) { if (!i.closest('tr').hidden) i.checked = e.target.checked; }); count(); });
        document.addEventListener('change', function (e) { if (e.target.matches('[data-item]')) count(); });
        document.querySelector('[data-filter]').addEventListener('input', function (e) {
          var q = e.target.value.toLowerCase().trim();
          document.querySelectorAll('[data-row]').forEach(function (r) { r.hidden = q && r.getAttribute('data-row').indexOf(q) === -1; });
        });
        count();
      })();
    </script>
  </x-slot:scripts>
</x-layouts.tenant>
