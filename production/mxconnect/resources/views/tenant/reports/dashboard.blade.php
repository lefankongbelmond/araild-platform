@php
  $c = $currency; $fmt = fn ($m) => $c->format((int) $m);
  $pct = fn ($v) => $v === null ? '—' : number_format($v, 1, ',', ' ') . ' %';
  $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
  $sel = 'mt-1 block w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm';
  $bars = function (array $rows, string $key) {
      $max = max(1, ...array_map(fn ($x) => (int) $x[$key], $rows ?: [[$key => 0]]));
      return array_map(fn ($x) => $x + ['w' => (int) round(((int) $x[$key]) * 100 / $max)], $rows);
  };
  $mem = $r['members']; $ct = $r['contributions']; $pr = $r['prestations'];
@endphp
<x-layouts.tenant title="Tableau de bord">
  <x-tenant.hero icon="chart" kicker="Pilotage" tone="brand">
    <x-slot:title>Tableau de bord de pilotage</x-slot:title>
    <x-slot:subtitle>{{ tenant('name') }} — du {{ $r['from']->format('d/m/Y') }} au {{ $r['to']->format('d/m/Y') }}@if ($filterLabel) · {{ $filterLabel }}@endif. Chiffres réels, calculés à l’instant ; montants en {{ $c->code }}.</x-slot:subtitle>
    <x-slot:actions>
      <button type="button" onclick="window.print()" class="print:hidden rounded-md border border-ink/10 bg-white px-4 py-2 text-sm font-medium text-ink/70 hover:bg-mist">Imprimer ou PDF</button>
      <a href="{{ route('reports.dashboard.export', request()->query()) }}" class="print:hidden rounded-md bg-pine px-4 py-2 text-sm font-medium text-white">Exporter (Excel)</a>
    </x-slot:actions>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <form method="GET" class="print:hidden mt-5 grid gap-3 rounded-lg border border-ink/10 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
    <label class="text-xs text-ink/60">Période<select name="periode" class="{{ $sel }}" onchange="document.getElementById('pl-libre').style.display = this.value === 'libre' ? '' : 'none'">
      @foreach ($periods as $k => [$l])<option value="{{ $k }}" @selected($periode === $k)>{{ $l }}</option>@endforeach
      <option value="libre" @selected($periode === 'libre')>Dates au choix</option></select></label>
    <div id="pl-libre" class="grid grid-cols-2 gap-2" @if ($periode !== 'libre') style="display:none" @endif>
      <label class="text-xs text-ink/60">Du<input type="date" name="du" value="{{ request('du', $r['from']->toDateString()) }}" class="{{ $sel }}"></label>
      <label class="text-xs text-ink/60">Au<input type="date" name="au" value="{{ request('au', $r['to']->toDateString()) }}" class="{{ $sel }}"></label>
    </div>
    <label class="text-xs text-ink/60">Formule<select name="formule" class="{{ $sel }}"><option value="">Toutes</option>@foreach ($guarantees as $id => $l)<option value="{{ $id }}" @selected($f['guarantee_id'] === $id)>{{ $l }}</option>@endforeach</select></label>
    <label class="text-xs text-ink/60">Prestataire<select name="prestataire" class="{{ $sel }}"><option value="">Tous</option>@foreach ($providers as $id => $l)<option value="{{ $id }}" @selected($f['provider_id'] === $id)>{{ $l }}</option>@endforeach</select></label>
    <label class="text-xs text-ink/60">Antenne (zone)<select name="antenne" class="{{ $sel }}"><option value="">Toutes</option>@foreach ($antennas as $id => $l)<option value="{{ $id }}" @selected($f['antenna_id'] === $id)>{{ $l }}</option>@endforeach</select></label>
    <label class="text-xs text-ink/60">Type de soins<select name="soins" class="{{ $sel }}"><option value="">Tous</option>@foreach ($categories as $id => $l)<option value="{{ $id }}" @selected($f['category_id'] === $id)>{{ $l }}</option>@endforeach</select></label>
    <label class="text-xs text-ink/60">Statut de traitement<select name="statut" class="{{ $sel }}"><option value="">Tous</option>@foreach (\App\Services\Tenant\PilotageService::STATUSES as $k => $l)<option value="{{ $k }}" @selected($f['status'] === $k)>{{ $l }}</option>@endforeach</select></label>
    @if ($health)<label class="text-xs text-ink/60">Adhérent (n°)<input name="adherent" maxlength="40" value="{{ $v['adherent'] ?? '' }}" class="{{ $sel }}" placeholder="Ex. MUT-000123"></label>@endif
    <div class="flex items-end gap-2"><button class="rounded-md bg-navy px-4 py-2 text-sm font-medium text-white">Afficher</button>
      <a href="{{ route('reports.dashboard') }}" class="rounded-md border border-ink/10 bg-white px-4 py-2 text-sm text-ink/70 hover:bg-mist">Effacer</a></div>
  </form>

  {{-- Adhésions --}}
  <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <x-tenant.kpi label="Adhérents" :value="$n($mem['total'])" icon="users" tone="brand" :hint="$n($mem['beneficiaries']) . ' bénéficiaire(s) couverts ou suspendus'" />
    <x-tenant.kpi label="Adhésions actives" :value="$n($mem['active'])" icon="shield" tone="leaf" :hint="$n($mem['pending']) . ' en attente de validation'" />
    <x-tenant.kpi label="Adhésions suspendues" :value="$n($mem['suspended'])" icon="alert" tone="amber" hint="Suspendus ou cotisations en retard" />
    <x-tenant.kpi label="Adhésions expirées" :value="$n($mem['expired'])" icon="history" tone="red" hint="Sortis, radiés ou sans formule en cours" />
  </div>

  {{-- Cotisations et prestations --}}
  <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <x-tenant.kpi label="Cotisations attendues" :value="$fmt($ct['expected'])" icon="coins" tone="brand" :hint="'Encaissé sur ces échéances : ' . $fmt($ct['paid_on_expected'])" />
    <x-tenant.kpi label="Taux de recouvrement" :value="$pct($ct['rate'])" icon="chart" tone="leaf" :hint="'Encaissé sur la période : ' . $fmt($ct['collected'])" />
    <x-tenant.kpi label="Prise en charge (mutuelle)" :value="$fmt($pr['covered'])" icon="clipboard" tone="amber" :hint="'Facturé : ' . $fmt($pr['billed']) . ' · tickets modérateurs : ' . $fmt($pr['copay'])" />
    <x-tenant.kpi label="Taux de sinistralité" :value="$pct($pr['loss_ratio'])" icon="pie" tone="violet" hint="Prise en charge ÷ cotisations encaissées" />
  </div>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="min-w-0 rounded-lg border border-ink/10 bg-white p-5 lg:col-span-2">
      <h2 class="font-semibold">Évolution mois par mois</h2>
      <p class="mt-1 text-xs text-ink/55">Cotisations encaissées et part de la mutuelle dans les soins validés (filtres appliqués). Survolez un mois pour ses montants.</p>
      <x-tenant.month-chart class="mt-3" :months="$r['monthly']" :currency="$c"
        :series="['collected' => ['Cotisations encaissées', '#0A5FB5'], 'mutual' => ['Part de la mutuelle (soins)', '#D97706']]" />
    </section>
    <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="font-semibold">Traitement des prestations</h2>
      <dl class="mt-2 divide-y divide-ink/5">
        <div class="flex justify-between gap-2 py-2"><dt class="text-ink/60">Validées</dt><dd class="font-semibold tabular-nums text-pine">{{ $n($pr['validated']) }}</dd></div>
        <div class="flex justify-between gap-2 py-2"><dt class="text-ink/60">En attente</dt><dd class="font-semibold tabular-nums">{{ $n($pr['pending']) }}</dd></div>
        <div class="flex justify-between gap-2 py-2"><dt class="text-ink/60">Rejetées</dt><dd class="font-semibold tabular-nums text-clay">{{ $n($pr['rejected']) }}</dd></div>
        <div class="flex justify-between gap-2 py-2"><dt class="text-ink/60">Arriérés de cotisations</dt><dd class="font-semibold tabular-nums">{{ $fmt($ct['overdue']) }}</dd></div>
      </dl>
    </section>
  </div>

  <div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="min-w-0 rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="font-semibold">Consommation par formule</h2>
      @forelse ($bars($r['by_formula'], 'mutual') as $x)
        <div class="mt-3"><div class="flex flex-wrap justify-between gap-2"><span>{{ $x['label'] }} <span class="text-xs text-ink/50">· {{ $n($x['count']) }} prestation(s)</span></span><b class="tabular-nums">{{ $fmt($x['mutual']) }}</b></div>
          <div class="mt-1 h-2 rounded-full bg-mist"><div class="h-2 rounded-full bg-pine" style="width:{{ $x['w'] }}%"></div></div></div>
      @empty<p class="mt-2 text-ink/55">Aucune prestation validée sur la période.</p>@endforelse
    </section>
    <section class="min-w-0 rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="font-semibold">Par établissement (10 premiers)</h2>
      @forelse ($bars($r['by_provider'], 'mutual') as $x)
        <div class="mt-3"><div class="flex flex-wrap justify-between gap-2"><span>{{ $x['label'] }} <span class="text-xs text-ink/50">· {{ $n($x['count']) }}</span></span><b class="tabular-nums">{{ $fmt($x['mutual']) }}</b></div>
          <div class="mt-1 h-2 rounded-full bg-mist"><div class="h-2 rounded-full bg-navy" style="width:{{ $x['w'] }}%"></div></div></div>
      @empty<p class="mt-2 text-ink/55">Aucune prestation validée sur la période.</p>@endforelse
    </section>
  </div>

  @if ($health)
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
      <section class="min-w-0 rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <h2 class="font-semibold">Consommation par garantie</h2>
        @forelse ($bars($r['by_rule'], 'mutual') as $x)
          <div class="mt-3"><div class="flex flex-wrap justify-between gap-2"><span>{{ $x['label'] }} <span class="text-xs text-ink/50">· {{ $n($x['count']) }}</span></span><b class="tabular-nums">{{ $fmt($x['mutual']) }}</b></div>
            <div class="mt-1 h-2 rounded-full bg-mist"><div class="h-2 rounded-full bg-amber-500" style="width:{{ $x['w'] }}%"></div></div></div>
        @empty<p class="mt-2 text-ink/55">Aucune prestation validée sur la période.</p>@endforelse
      </section>
      <section class="min-w-0 rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <h2 class="font-semibold">Consommation pharmaceutique</h2>
        @php $ph = $r['pharmacy']; @endphp
        <dl class="mt-2 grid grid-cols-2 gap-2">
          <div><dt class="text-xs text-ink/50">Délivrances</dt><dd class="font-semibold">{{ $n($ph['count']) }}</dd></div>
          <div><dt class="text-xs text-ink/50">Montant délivré</dt><dd class="font-semibold">{{ $fmt($ph['total']) }}</dd></div>
          <div><dt class="text-xs text-ink/50">Part mutuelle (validée)</dt><dd class="font-semibold">{{ $fmt($ph['mutual']) }}</dd></div>
          <div><dt class="text-xs text-ink/50">Non pris en charge</dt><dd class="font-semibold">{{ $fmt($ph['excluded']) }}</dd></div>
        </dl>
        @if ($ph['top'])
          <h3 class="mt-3 text-xs font-semibold uppercase tracking-wide text-ink/50">Médicaments les plus délivrés</h3>
          <ul class="mt-1 space-y-1">@foreach ($ph['top'] as $x)<li class="flex justify-between gap-2"><span>{{ $x['label'] }} <span class="text-xs text-ink/50">× {{ $n($x['qty']) }}</span></span><span class="tabular-nums">{{ $fmt($x['amount']) }}</span></li>@endforeach</ul>
        @endif
      </section>
    </div>
  @endif

  <section class="mt-6 min-w-0 rounded-lg border border-ink/10 bg-white p-5 text-sm">
    <h2 class="font-semibold">Plafonds consommés ({{ $r['to']->year }})</h2>
    <p class="mt-1 text-xs text-ink/55">Plafonds annuels en montant des formules à garanties détaillées : titulaires (familles ou bénéficiaires) ayant consommé au moins 80 % ou la totalité.</p>
    @if ($r['limits'])
      <div class="relative mt-3 overflow-x-auto">
        <table class="w-full text-sm" style="min-width:560px">
          <thead class="text-left text-ink/50"><tr><th class="py-1 pr-3 font-medium">Plafond</th><th class="py-1 pr-3 font-medium">Montant</th><th class="py-1 pr-3 text-right font-medium">Concernés</th><th class="py-1 pr-3 text-right font-medium">≥ 80 %</th><th class="py-1 pr-3 text-right font-medium">Épuisés</th><th class="py-1 text-right font-medium">Consommé</th></tr></thead>
          <tbody class="divide-y divide-ink/5">
            @foreach ($r['limits'] as $x)
              <tr><td class="py-2 pr-3">{{ $x['label'] }}<div class="text-xs text-ink/50">{{ $x['formula'] }} · par {{ $x['holder'] === 'familles' ? 'famille' : 'bénéficiaire' }}</div></td><td class="py-2 pr-3 tabular-nums">{{ $fmt($x['amount']) }}</td>
                <td class="py-2 pr-3 text-right tabular-nums">{{ $n($x['holders']) }}</td><td class="py-2 pr-3 text-right tabular-nums text-amber-700">{{ $n($x['over80']) }}</td>
                <td class="py-2 pr-3 text-right tabular-nums text-clay">{{ $n($x['full']) }}</td><td class="py-2 text-right tabular-nums">{{ $fmt($x['consumed']) }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="mt-2 text-ink/55">Aucun plafond annuel paramétré dans les formules en cours (Catalogue des formules).</p>
    @endif
  </section>
  <p class="mt-4 text-xs text-ink/50">La « zone » correspond aux antennes de la mutuelle (la région n’est pas enregistrée sur les fiches). Le détail par garantie, la pharmacie et le filtre par adhérent sont réservés aux personnes habilitées aux données de santé. Rapport complet (trésorerie, factures) : <a href="{{ route('reports.index') }}" class="text-pine underline">Rapports d’activité</a>.</p>
</x-layouts.tenant>
