@php
  $fmt = fn ($minor) => $currency->format((int) $minor);
  $f = 'h-11 w-full rounded-xl border border-ink/10 bg-white px-3 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100';
  $oldActs = old('acts', request('acts', [[]]));
  $oldMeds = old('meds', request('meds', [[]]));
  $r = $result;
@endphp
<x-layouts.tenant title="Calcul de quote-part">
  <x-tenant.hero icon="calculator" kicker="Facturation · Formations sanitaires" tone="teal" class="print:hidden">
    <x-slot:title>Calcul de quote-part</x-slot:title>
    <x-slot:subtitle>Avant les soins ou à l’accueil d’une formation sanitaire : vérifiez les droits de l’adhérent et calculez la part prise en charge par la mutuelle et le ticket modérateur à payer, selon la garantie et ses plafonds.</x-slot:subtitle>
  </x-tenant.hero>

  <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
    <form method="POST" action="{{ route('billing.quote.compute') }}" class="h-max space-y-5 print:hidden" id="qp-form">
      @csrf
      <x-tenant.panel title="Adhérent et garantie" icon="users">
        <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
          <label class="block"><span class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Code adhérent</span>
            <input name="member_code" value="{{ old('member_code', request('member_code')) }}" placeholder="Ex. MUT-000123" class="{{ $f }} font-mono" autocomplete="off"></label>
          <label class="block"><span class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">ou garantie (simulation sans adhérent)</span>
            <select name="guarantee_id" class="{{ $f }}"><option value="">—</option>@foreach ($guarantees as $g)<option value="{{ $g->id }}" @selected((string) old('guarantee_id', request('guarantee_id')) === (string) $g->id)>{{ $g->name }}</option>@endforeach</select></label>
          <label class="block"><span class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Date des soins <span class="text-clay">*</span></span>
            <input type="date" name="care_date" value="{{ old('care_date', request('care_date', now()->toDateString())) }}" required class="{{ $f }}"></label>
          <label class="block"><span class="mb-1.5 block text-[13px] font-semibold text-[#0B1F3A]">Formation sanitaire</span>
            <input name="facility" value="{{ old('facility', request('facility')) }}" placeholder="Ex. CSI de Mvog-Ada" class="{{ $f }}"></label>
        </div>
      </x-tenant.panel>

      <x-tenant.panel title="Actes" icon="stethoscope">
        <x-slot:aside><button type="button" class="font-semibold text-brand-600 hover:underline" data-add="acts">+ Ajouter un acte</button></x-slot:aside>
        <div class="space-y-2.5 p-5 sm:p-6" data-rows="acts">
          @foreach ($oldActs as $i => $row)
            <div class="grid grid-cols-[minmax(0,1fr)_110px_64px] gap-2" data-row>
              <select name="acts[{{ $i }}][id]" class="{{ $f }}" aria-label="Acte"><option value="">Choisir un acte…</option>
                @foreach ($acts->groupBy('category') as $cat => $list)<optgroup label="{{ $cat ?: 'Autres' }}">@foreach ($list as $a)<option value="{{ $a->id }}" data-tariff="{{ $a->tariff_minor ? number_format($a->tariff_minor / (10 ** $currency->minor_unit), $currency->minor_unit, '.', '') : '' }}" @selected((string) ($row['id'] ?? '') === (string) $a->id)>{{ $a->label }}</option>@endforeach</optgroup>@endforeach
              </select>
              <input name="acts[{{ $i }}][amount]" value="{{ $row['amount'] ?? '' }}" inputmode="decimal" placeholder="Montant" class="{{ $f }} tabular-nums" aria-label="Montant facturé">
              <input name="acts[{{ $i }}][qty]" value="{{ $row['qty'] ?? 1 }}" inputmode="numeric" class="{{ $f }} text-center" aria-label="Quantité">
            </div>
          @endforeach
          @if ($acts->isEmpty())<p class="text-sm text-ink/50">Aucun acte au catalogue. <a class="font-semibold text-brand-600 hover:underline" href="{{ route('catalog.import', 'actes') }}">Importer la nomenclature de référence</a>.</p>@endif
          <p class="text-[11.5px] text-ink/45">Montant facturé par la formation sanitaire, en {{ $currency->code }}. Laissez vide pour utiliser le tarif conventionnel de l’acte.</p>
        </div>
      </x-tenant.panel>

      <x-tenant.panel title="Médicaments" icon="pill">
        <x-slot:aside><button type="button" class="font-semibold text-brand-600 hover:underline" data-add="meds">+ Ajouter un médicament</button></x-slot:aside>
        <div class="space-y-2.5 p-5 sm:p-6" data-rows="meds">
          @foreach ($oldMeds as $i => $row)
            <div class="grid grid-cols-[minmax(0,1fr)_110px_64px] gap-2" data-row>
              <select name="meds[{{ $i }}][id]" class="{{ $f }}" aria-label="Médicament"><option value="">Choisir un médicament…</option>
                @foreach ($medicines as $m)<option value="{{ $m->id }}" @selected((string) ($row['id'] ?? '') === (string) $m->id)>{{ $m->label }}{{ $m->presentation ? ' — ' . $m->presentation : '' }}</option>@endforeach
              </select>
              <input name="meds[{{ $i }}][price]" value="{{ $row['price'] ?? '' }}" inputmode="decimal" placeholder="Prix unit." class="{{ $f }} tabular-nums" aria-label="Prix unitaire">
              <input name="meds[{{ $i }}][qty]" value="{{ $row['qty'] ?? 1 }}" inputmode="numeric" class="{{ $f }} text-center" aria-label="Quantité">
            </div>
          @endforeach
          <p class="text-[11.5px] text-ink/45">Laissez le prix vide pour utiliser le prix de référence du catalogue (s’il est dans la devise de la mutuelle).</p>
        </div>
      </x-tenant.panel>

      <button class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 text-[15px] font-bold text-white shadow-lg shadow-leaf-500/25"><x-tenant.icon name="calculator" :size="18"/> Calculer la quote-part</button>
    </form>

    {{-- Résultat --}}
    <div class="h-max xl:sticky xl:top-24">
      @if (! $r)
        <x-tenant.panel class="print:hidden">
          <div class="px-6 py-16 text-center">
            <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-teal-50 text-teal-600"><x-tenant.icon name="calculator" :size="30"/></span>
            <h3 class="mt-4 font-bold text-[#0B1F3A]">Le bon de prise en charge s’affichera ici</h3>
            <p class="mx-auto mt-1 max-w-sm text-sm text-ink/55">Saisissez l’adhérent (ou une garantie), les actes et les médicaments, puis lancez le calcul. Il s’agit d’une simulation : rien n’est enregistré.</p>
          </div>
        </x-tenant.panel>
      @else
        <section class="overflow-hidden rounded-3xl bg-white shadow-soft ring-1 ring-ink/[.05] print:shadow-none print:ring-0">
          <div class="flex items-start justify-between gap-3 bg-gradient-to-r from-[#0B2A5B] to-brand-500 px-6 py-5 text-white">
            <div><div class="text-xs font-semibold uppercase tracking-wider text-white/70">Bon de prise en charge — simulation</div>
              <div class="mt-0.5 text-lg font-bold">{{ tenant('name') }}</div>
              <div class="text-xs text-white/70">Réf. {{ $r['reference'] }} · soins du {{ \Illuminate\Support\Carbon::parse($r['date'])->format('d/m/Y') }}@if($r['facility']) · {{ $r['facility'] }}@endif</div></div>
            <button type="button" onclick="window.print()" class="grid h-10 w-10 place-items-center rounded-xl bg-white/15 hover:bg-white/25 print:hidden" aria-label="Imprimer"><x-tenant.icon name="printer" :size="18"/></button>
          </div>

          @if ($r['errors'])
            <div class="mx-6 mt-5 rounded-xl border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc space-y-1 pl-4">@foreach ($r['errors'] as $er)<li>{{ $er }}</li>@endforeach</ul></div>
          @endif

          <dl class="grid grid-cols-2 gap-3 px-6 pt-5 text-sm">
            <div><dt class="text-xs text-ink/50">Adhérent</dt><dd class="font-semibold text-[#0B1F3A]">{{ $r['member'] ? $r['member']->last_name . ' ' . $r['member']->first_name . ' · ' . $r['member']->member_code : 'Simulation sans adhérent' }}</dd></div>
            <div><dt class="text-xs text-ink/50">Garantie</dt><dd class="font-semibold text-[#0B1F3A]">{{ $r['version']?->guarantee?->name ?? '—' }}@if($r['version']) <span class="font-normal text-ink/50">· {{ rtrim(rtrim(number_format((float) $r['version']->coverage_rate, 2, ',', ' '), '0'), ',') }} % pris en charge</span>@endif</dd></div>
          </dl>

          @if ($r['verdict'])
            <div @class(['mx-6 mt-4 rounded-xl px-4 py-3 text-sm', 'bg-leaf-50 text-leaf-700 ring-1 ring-leaf-100' => $r['verdict']['ok'], 'bg-clay/5 text-clay ring-1 ring-clay/20' => ! $r['verdict']['ok']])>
              @if ($r['verdict']['ok'])<b>Droits ouverts</b> à la date des soins.
              @else<b>Droits non ouverts :</b> {{ collect($r['verdict']['reasons'])->map(fn ($x) => $rightsLabels[$x] ?? $x)->implode(' ') }}@endif
            </div>
          @endif

          @if ($r['lines'])
            <div class="mt-4 overflow-x-auto">
              <table class="w-full text-sm">
                <thead><tr class="bg-[#EEF4FC] text-left text-[12px] font-semibold text-[#0B1F3A]/80"><th class="py-2.5 pl-6 pr-2">Prestation</th><th class="px-2 py-2.5 text-right">Montant</th><th class="px-2 py-2.5 text-right">Mutuelle</th><th class="py-2.5 pl-2 pr-6 text-right">Adhérent</th></tr></thead>
                <tbody class="divide-y divide-ink/[.06]">
                  @foreach ($r['lines'] as $l)
                    <tr><td class="py-2.5 pl-6 pr-2"><div class="font-medium text-[#0B1F3A]">{{ $l['label'] }}</div><div class="text-xs text-ink/50">{{ $l['type'] }} · {{ $l['qty'] }} × {{ $fmt($l['unit']) }}@if($l['capped']) · <span class="font-semibold text-amber-700">plafond {{ ['annual' => 'annuel', 'per_act' => 'par acte', 'per_stay' => 'par séjour'][$l['reason']] ?? '' }} atteint</span>@endif</div></td>
                      <td class="whitespace-nowrap px-2 py-2.5 text-right tabular-nums">{{ $fmt($l['total']) }}</td>
                      <td class="whitespace-nowrap px-2 py-2.5 text-right font-semibold tabular-nums text-leaf-700">{{ $fmt($l['mutual_minor']) }}</td>
                      <td class="whitespace-nowrap py-2.5 pl-2 pr-6 text-right font-semibold tabular-nums text-amber-700">{{ $fmt($l['beneficiary_minor']) }}</td></tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            <div class="grid grid-cols-3 gap-3 p-6">
              <div class="rounded-2xl bg-paper p-4"><div class="text-xs text-ink/50">Total des soins</div><div class="mt-1 text-lg font-extrabold tabular-nums text-[#0B1F3A]">{{ $fmt($r['total']) }}</div></div>
              <div class="rounded-2xl bg-leaf-50 p-4 ring-1 ring-leaf-100"><div class="text-xs text-leaf-700">Part mutuelle</div><div class="mt-1 text-lg font-extrabold tabular-nums text-leaf-700">{{ $fmt($r['mutual']) }}</div></div>
              <div class="rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-200"><div class="text-xs text-amber-800">À payer par l’adhérent</div><div class="mt-1 text-lg font-extrabold tabular-nums text-amber-800">{{ $fmt($r['member_part']) }}</div></div>
            </div>
          @endif
          <p class="border-t border-ink/[.06] px-6 py-3 text-[11.5px] text-ink/45">Simulation calculée selon la garantie en vigueur à la date des soins et ses plafonds{{ $r['member'] ? ' (consommation de l’année déjà déduite)' : '' }}. Le montant définitif est fixé à la saisie et à la validation de la prestation.</p>
        </section>
      @endif
    </div>
  </div>

  <x-slot:scripts>
    <script>
      document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-add]'); if (!b) return;
        var box = document.querySelector('[data-rows="' + b.dataset.add + '"]'), rows = box.querySelectorAll('[data-row]');
        var last = rows[rows.length - 1], n = rows.length, c = last.cloneNode(true);
        c.querySelectorAll('select, input').forEach(function (el) {
          el.name = el.name.replace(/\[\d+\]/, '[' + n + ']');
          if (el.tagName === 'SELECT') el.selectedIndex = 0; else el.value = el.name.indexOf('[qty]') > -1 ? '1' : '';
        });
        last.after(c);
      });
      // Tarif conventionnel proposé dans le montant quand il existe.
      document.addEventListener('change', function (e) {
        var s = e.target; if (!s.matches('[data-rows="acts"] select')) return;
        var t = s.options[s.selectedIndex] && s.options[s.selectedIndex].getAttribute('data-tariff'), amt = s.parentNode.querySelector('input[name$="[amount]"]');
        if (t && amt && !amt.value) amt.value = t;
      });
    </script>
  </x-slot:scripts>
</x-layouts.tenant>
