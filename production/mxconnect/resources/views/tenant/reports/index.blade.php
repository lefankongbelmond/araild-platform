@php
  $r = $report; $c = $currency; $fmt = fn ($m) => $c->format((int) $m);
  $pct = fn ($v) => $v === null ? '—' : number_format($v, 1, ',', ' ') . ' %';
  $f = 'rounded-md border border-ink/15 bg-white px-3 py-2 text-sm';
  $line = fn ($label, $value, $hint = null) => '<div class="flex flex-wrap justify-between gap-2 py-2"><dt class="text-ink/60">' . e($label) . ($hint ? '<span class="block text-xs text-ink/45">' . e($hint) . '</span>' : '') . '</dt><dd class="font-semibold tabular-nums">' . e($value) . '</dd></div>';
@endphp
<x-layouts.tenant title="Rapports d’activité">
  <x-tenant.hero icon="chart" kicker="Facturation et finances" tone="violet">
    <x-slot:title>Rapport d’activité</x-slot:title>
    <x-slot:subtitle>{{ tenant('name') }} — du {{ $r['from']->format('d/m/Y') }} au {{ $r['to']->format('d/m/Y') }}. Chiffres réels, calculés à l’instant depuis les données de la mutuelle ; montants en {{ $c->code }}.</x-slot:subtitle>
    <x-slot:actions>
      <button type="button" onclick="window.print()" class="print:hidden rounded-md border border-ink/10 bg-white px-4 py-2 text-sm font-medium text-ink/70 hover:bg-mist">Imprimer</button>
      <a href="{{ route('reports.export', request()->query()) }}" class="print:hidden rounded-md bg-pine px-4 py-2 text-sm font-medium text-white">Exporter (CSV)</a>
    </x-slot:actions>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <form method="GET" class="print:hidden mt-5 flex flex-wrap items-end gap-2">
    <label class="text-xs text-ink/60">Période<select name="periode" class="{{ $f }} mt-1 block" onchange="document.getElementById('rp-libre').style.display = this.value === 'libre' ? '' : 'none'">
      @foreach ($periods as $k => [$l]) <option value="{{ $k }}" @selected($periode === $k)>{{ $l }}</option>@endforeach
      <option value="libre" @selected($periode === 'libre')>Dates au choix</option>
    </select></label>
    <span id="rp-libre" class="flex flex-wrap gap-2" @if ($periode !== 'libre') style="display:none" @endif>
      <label class="text-xs text-ink/60">Du<input type="date" name="du" value="{{ request('du', $r['from']->toDateString()) }}" class="{{ $f }} mt-1 block"></label>
      <label class="text-xs text-ink/60">Au<input type="date" name="au" value="{{ request('au', $r['to']->toDateString()) }}" class="{{ $f }} mt-1 block"></label>
    </span>
    <button class="rounded-md bg-navy px-4 py-2 text-sm font-medium text-white">Afficher</button>
  </form>

  <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-tenant.kpi label="Adhérents actifs" :value="number_format($r['members']['active'], 0, ',', ' ')" icon="users" tone="brand" :hint="$r['members']['new'] . ' nouveau(x) sur la période'" />
    <x-tenant.kpi label="Cotisations encaissées" :value="$fmt($r['contributions']['collected'])" icon="coins" tone="leaf" :hint="'Recouvrement : ' . $pct($r['contributions']['rate'])" />
    <x-tenant.kpi label="Part de la mutuelle" :value="$fmt($r['prestations']['mutual'])" icon="clipboard" tone="amber" :hint="$r['prestations']['validated'] . ' prestation(s) validée(s)'" />
    <x-tenant.kpi label="Prestations / cotisations" :value="$pct($r['prestations']['claims_ratio'])" icon="chart" tone="violet" hint="Part mutuelle ÷ encaissements" />
  </div>

  <section class="mt-6 min-w-0 rounded-lg border border-ink/10 bg-white p-5">
    <h2 class="font-semibold">Évolution mois par mois</h2>
    <p class="mt-1 text-xs text-ink/55">Cotisations encaissées et part de la mutuelle dans les soins validés, par mois.@if (count($r['monthly']) >= \App\Services\Tenant\ActivityReportService::MAX_MONTHS) Les {{ \App\Services\Tenant\ActivityReportService::MAX_MONTHS }} derniers mois de la période.@endif Survolez un mois pour ses montants.</p>
    <x-tenant.month-chart class="mt-3" :months="$r['monthly']" :currency="$c"
      :series="['collected' => ['Cotisations encaissées', '#0A5FB5'], 'mutual' => ['Part de la mutuelle (soins)', '#D97706']]" />
    <div class="relative mt-4 overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-ink/50"><tr>
          <th class="py-1 pr-3 font-medium">Mois</th><th class="py-1 pr-3 text-right font-medium">Nouveaux adhérents</th><th class="py-1 pr-3 text-right font-medium">Cotisations encaissées</th>
          <th class="py-1 pr-3 text-right font-medium">Prestations validées</th><th class="py-1 pr-3 text-right font-medium">Part mutuelle</th><th class="py-1 pr-3 text-right font-medium">Entrées</th><th class="py-1 text-right font-medium">Sorties</th>
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @foreach ($r['monthly'] as $mo)
            <tr><td class="py-2 pr-3 whitespace-nowrap">{{ $mo['label'] }}</td><td class="py-2 pr-3 text-right tabular-nums">{{ $mo['new'] }}</td>
              <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">{{ $fmt($mo['collected']) }}</td><td class="py-2 pr-3 text-right tabular-nums">{{ $mo['prestations'] }}</td>
              <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">{{ $fmt($mo['mutual']) }}</td><td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">{{ $fmt($mo['in']) }}</td>
              <td class="py-2 text-right tabular-nums whitespace-nowrap">{{ $fmt($mo['out']) }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  <div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="min-w-0 rounded-lg border border-ink/10 bg-white p-5">
      <h2 class="font-semibold">Adhérents et souscriptions</h2>
      <dl class="mt-2 divide-y divide-ink/5 text-sm">
        {!! $line('Adhérents actifs (aujourd’hui)', number_format($r['members']['active'], 0, ',', ' ')) !!}
        {!! $line('Nouveaux adhérents', $r['members']['new'], 'date d’adhésion dans la période') !!}
        {!! $line('Personnes à charge actives', $r['members']['dependents']) !!}
        {!! $line('Bénéficiaires couverts', $r['members']['active'] + $r['members']['dependents']) !!}
        {!! $line('Adhérents suspendus (aujourd’hui)', $r['members']['suspended']) !!}
        {!! $line('Souscriptions validées', $r['subscriptions']['validated']) !!}
        {!! $line('Souscriptions en attente de validation', $r['subscriptions']['pending']) !!}
      </dl>
    </section>
    <section class="min-w-0 rounded-lg border border-ink/10 bg-white p-5">
      <h2 class="font-semibold">Cotisations</h2>
      <dl class="mt-2 divide-y divide-ink/5 text-sm">
        {!! $line('Cotisations appelées', $fmt($r['contributions']['called']), 'échéances de la période') !!}
        {!! $line('… dont déjà encaissées', $fmt($r['contributions']['collected_on_called'])) !!}
        {!! $line('Taux de recouvrement', $pct($r['contributions']['rate'])) !!}
        {!! $line('Encaissements de la période', $fmt($r['contributions']['collected']), 'toutes échéances confondues') !!}
        {!! $line('Arriérés (aujourd’hui)', $fmt($r['contributions']['overdue'])) !!}
        @if ($r['contributions']['fees'] ?? 0){!! $line('Droits d’adhésion encaissés', $fmt($r['contributions']['fees']), 'en plus des cotisations') !!}@endif
      </dl>
    </section>
    <section class="min-w-0 rounded-lg border border-ink/10 bg-white p-5">
      <h2 class="font-semibold">Prestations</h2>
      <dl class="mt-2 divide-y divide-ink/5 text-sm">
        {!! $line('Prestations validées', $r['prestations']['validated'], 'date des soins dans la période') !!}
        {!! $line('Montant total des soins', $fmt($r['prestations']['total'])) !!}
        {!! $line('Part de la mutuelle', $fmt($r['prestations']['mutual'])) !!}
        {!! $line('Part des adhérents (ticket modérateur)', $fmt($r['prestations']['beneficiary'])) !!}
        {!! $line('En attente de validation', $r['prestations']['pending']) !!}
        {!! $line('Rejetées', $r['prestations']['rejected']) !!}
        {!! $line('Ratio prestations / cotisations encaissées', $pct($r['prestations']['claims_ratio'])) !!}
      </dl>
    </section>
    <section class="min-w-0 rounded-lg border border-ink/10 bg-white p-5">
      <h2 class="font-semibold">Trésorerie et tiers payant</h2>
      <dl class="mt-2 divide-y divide-ink/5 text-sm">
        {!! $line('Entrées de trésorerie', $fmt($r['treasury']['in'])) !!}
        {!! $line('Sorties de trésorerie', $fmt($r['treasury']['out'])) !!}
        {!! $line('Solde de la période', $fmt($r['treasury']['in'] - $r['treasury']['out'])) !!}
        {!! $line('Factures des prestataires payées', $fmt($r['invoices']['paid'])) !!}
        {!! $line('Factures des prestataires à payer (aujourd’hui)', $fmt($r['invoices']['payable'])) !!}
      </dl>
    </section>
    <section class="min-w-0 rounded-lg border border-ink/10 bg-white p-5">
      <h2 class="font-semibold">Par prestataire</h2>
      <table class="mt-2 w-full text-sm"><thead class="text-left text-ink/50"><tr><th class="py-1 font-medium">Prestataire</th><th class="py-1 text-right font-medium">Prestations</th><th class="py-1 text-right font-medium">Part mutuelle</th></tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($r['by_provider'] as $p)<tr><td class="py-2">{{ $p['label'] }}</td><td class="py-2 text-right tabular-nums">{{ $p['count'] }}</td><td class="py-2 text-right tabular-nums">{{ $fmt($p['mutual']) }}</td></tr>
          @empty<tr><td colspan="3" class="py-4 text-center text-ink/45">Aucune prestation validée sur la période.</td></tr>@endforelse
        </tbody></table>
    </section>
    <section class="min-w-0 rounded-lg border border-ink/10 bg-white p-5">
      <h2 class="font-semibold">Par acte</h2>
      @if ($health)
        <table class="mt-2 w-full text-sm"><thead class="text-left text-ink/50"><tr><th class="py-1 font-medium">Acte</th><th class="py-1 text-right font-medium">Prestations</th><th class="py-1 text-right font-medium">Part mutuelle</th></tr></thead>
          <tbody class="divide-y divide-ink/5">
            @forelse ($r['by_act'] as $a)<tr><td class="py-2">{{ $a['label'] }}</td><td class="py-2 text-right tabular-nums">{{ $a['count'] }}</td><td class="py-2 text-right tabular-nums">{{ $fmt($a['mutual']) }}</td></tr>
            @empty<tr><td colspan="3" class="py-4 text-center text-ink/45">Aucune prestation validée sur la période.</td></tr>@endforelse
          </tbody></table>
      @else
        <p class="mt-2 text-sm text-ink/55">Réservé aux rôles qui voient le détail médical (administrateur, contrôleur-validateur, gestionnaire des prestations).</p>
      @endif
    </section>
  </div>
  <p class="mt-4 text-xs text-ink/45">Édité le {{ now()->format('d/m/Y à H:i') }} par {{ auth()->user()?->name }} · MX-CONNECT. Aucune conversion de devise. Les montants « aujourd’hui » ne dépendent pas de la période.</p>
</x-layouts.tenant>
