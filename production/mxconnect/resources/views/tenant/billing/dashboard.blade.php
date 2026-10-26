@php
  $fmt = fn ($minor) => $currency->format((int) $minor);
  $max = max(1, collect($months)->map(fn ($m) => max($m['called'], $m['collected']))->max());
  $monthNames = ['Janv.', 'Févr.', 'Mars', 'Avr.', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'];
@endphp
<x-layouts.tenant title="Facturation">
  <x-tenant.hero icon="receipt" kicker="Facturation et finances" tone="leaf">
    <x-slot:title>Facturation</x-slot:title>
    <x-slot:subtitle>Cotisations appelées et encaissées, retards, factures des formations sanitaires et prestations prises en charge — chiffres réels de votre mutuelle.</x-slot:subtitle>
    <x-slot:actions>
      <form method="GET" class="flex items-center gap-2">
        <label for="b-year" class="text-sm font-medium text-ink/60">Exercice</label>
        <select id="b-year" name="annee" onchange="this.form.submit()" class="h-10 rounded-xl border border-ink/10 bg-white px-3 text-sm font-semibold">
          @for ($y = now()->year + 1; $y >= now()->year - 5; $y--)<option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>@endfor
        </select>
      </form>
      <a href="{{ route('billing.quote') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-4 text-sm font-bold text-white shadow-lg shadow-leaf-500/25"><x-tenant.icon name="calculator" :size="17"/> Calculer une quote-part</a>
    </x-slot:actions>
  </x-tenant.hero>

  <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-tenant.kpi label="Cotisations appelées" :value="$fmt($kpi['called'])" icon="file" tone="brand" />
    <x-tenant.kpi label="Cotisations encaissées" :value="$fmt($kpi['collected'])" icon="coins" tone="leaf" :hint="$kpi['payments'] . ' paiement(s)'" />
    <x-tenant.kpi label="Taux de recouvrement" :value="$kpi['rate'] !== null ? number_format($kpi['rate'], 1, ',', ' ') . ' %' : '—'" icon="chart" tone="violet" />
    <x-tenant.kpi label="Retards de cotisation (toutes années)" :value="$fmt($kpi['overdue_amount'])" icon="alert" tone="red" :hint="$kpi['overdue_count'] . ' échéance(s)'" />
  </div>

  <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
    <x-tenant.panel :title="'Cotisations ' . $year . ' : appelées et encaissées'" icon="chart">
      <x-slot:aside>
        <span class="inline-flex items-center gap-1.5 text-xs text-ink/60"><span class="h-2.5 w-2.5 rounded-sm bg-brand-100 ring-1 ring-brand-500/40"></span>Appelées</span>
        <span class="inline-flex items-center gap-1.5 text-xs text-ink/60"><span class="h-2.5 w-2.5 rounded-sm bg-leaf-500"></span>Encaissées</span>
      </x-slot:aside>
      <div class="px-5 pb-5 pt-6 sm:px-6">
        <div class="flex h-56 items-end gap-2 sm:gap-3" role="img" aria-label="Cotisations appelées et encaissées par mois">
          @foreach ($months as $m => $v)
            <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
              <div class="flex h-full w-full items-end justify-center gap-0.5">
                <div class="w-1/2 rounded-t-md bg-brand-100 ring-1 ring-brand-500/30" style="height: {{ round($v['called'] * 100 / $max, 1) }}%" title="Appelées : {{ $fmt($v['called']) }}"></div>
                <div class="w-1/2 rounded-t-md bg-gradient-to-t from-leaf-600 to-leaf-500" style="height: {{ round($v['collected'] * 100 / $max, 1) }}%" title="Encaissées : {{ $fmt($v['collected']) }}"></div>
              </div>
              <span class="text-[10.5px] font-medium text-ink/45">{{ $monthNames[$m - 1] }}</span>
            </div>
          @endforeach
        </div>
        @if ($kpi['called'] === 0 && $kpi['collected'] === 0)
          <p class="mt-4 text-center text-sm text-ink/50">Aucune cotisation appelée ni encaissée en {{ $year }}. Les échéances sont créées à la validation des souscriptions.</p>
        @endif
      </div>
    </x-tenant.panel>

    <x-tenant.panel title="Factures des formations sanitaires" icon="receipt">
      <ul class="mt-3 divide-y divide-ink/[.06] px-5 pb-4 sm:px-6">
        @foreach ($invoiceStatuses as $st => $label)
          @php $row = $invoices[$st] ?? null; @endphp
          <li class="flex items-center justify-between py-3 text-sm">
            <span class="text-ink/70">{{ $label }}</span>
            <span class="text-right"><b class="tabular-nums text-[#0B1F3A]">{{ $row ? $fmt($row->total) : '—' }}</b><span class="ml-2 text-xs text-ink/45">{{ (int) ($row->n ?? 0) }}</span></span>
          </li>
        @endforeach
      </ul>
      <div class="border-t border-ink/[.06] px-6 py-3 text-xs text-ink/50">Factures du tiers payant, saisies dans les dossiers de <a class="font-semibold text-brand-600 hover:underline" href="{{ route('claims.index') }}">recours</a>.</div>
    </x-tenant.panel>
  </div>

  <div class="mt-5 grid gap-5 lg:grid-cols-2">
    <x-tenant.panel :title="'Prestations ' . $year" icon="clipboard">
      <div class="grid grid-cols-3 gap-3 p-5 sm:p-6">
        @foreach ([['Montant des soins', $prest->total ?? 0, 'text-[#0B1F3A]'], ['Part mutuelle', $prest->mutual ?? 0, 'text-leaf-700'], ['Part adhérents', $prest->member ?? 0, 'text-amber-700']] as [$l, $v, $c])
          <div class="rounded-2xl bg-paper p-4"><div class="text-xs font-medium text-ink/50">{{ $l }}</div><div class="mt-1 text-lg font-extrabold tabular-nums {{ $c }}">{{ $fmt($v) }}</div></div>
        @endforeach
      </div>
      @php $tot = (int) ($prest->total ?? 0); $mp = $tot > 0 ? round(($prest->mutual ?? 0) * 100 / $tot) : 0; @endphp
      <div class="px-5 pb-6 sm:px-6">
        <div class="flex h-3 overflow-hidden rounded-full bg-amber-100" role="img" aria-label="Part mutuelle {{ $mp }} %"><div class="bg-gradient-to-r from-leaf-500 to-leaf-600" style="width: {{ $mp }}%"></div></div>
        <p class="mt-2 text-xs text-ink/50">{{ (int) ($prest->n ?? 0) }} prestation(s) hors rejets · prise en charge moyenne {{ $mp }} %</p>
      </div>
    </x-tenant.panel>

    <x-tenant.panel title="Derniers encaissements" icon="coins">
      <x-slot:aside><a class="font-semibold text-brand-600 hover:underline" href="{{ route('contributions.index') }}">Toutes les cotisations</a></x-slot:aside>
      @if ($recentPayments->isEmpty())
        <p class="px-6 py-10 text-center text-sm text-ink/50">Aucun encaissement enregistré.</p>
      @else
        <ul class="mt-3 divide-y divide-ink/[.06] px-5 pb-4 sm:px-6">
          @foreach ($recentPayments as $p)
            @php $mem = $p->schedule?->subscription?->member; @endphp
            <li class="flex items-center justify-between gap-3 py-3 text-sm">
              <span class="min-w-0"><b class="block truncate text-[#0B1F3A]">{{ $mem ? $mem->last_name . ' ' . $mem->first_name : '—' }}</b><span class="text-xs text-ink/50">{{ \Illuminate\Support\Carbon::parse($p->paid_on)->format('d/m/Y') }} · {{ ['mobile_money' => 'Mobile Money', 'cash' => 'Espèces', 'group' => 'Groupement'][$p->mode] ?? $p->mode }}</span></span>
              <b class="tabular-nums text-leaf-700">{{ $fmt($p->amount_minor) }}</b>
            </li>
          @endforeach
        </ul>
      @endif
    </x-tenant.panel>
  </div>
</x-layouts.tenant>
