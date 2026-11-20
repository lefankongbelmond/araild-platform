@php
  use App\Support\ProviderAccess;
  $c = $d['counts'];
  // Mois abrégés dans la langue affichée (Carbon suit la langue de l'interface).
  $label = fn ($ym) => \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $ym . '-01')->translatedFormat('M y');
  $countMonths = collect($d['months'])->map(fn ($ym) => ['label' => $label($ym), 'care' => $d['series']['care'][$ym], 'invoices' => $d['series']['invoices'][$ym]])->all();
  $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
  $hasMutuals = count($d['mutuals']) > 0;
  $tones = ['danger' => 'border-clay/30 bg-clay/5 text-clay', 'warning' => 'border-amber-200 bg-amber-50 text-amber-800', 'success' => 'border-leaf-100 bg-leaf-50 text-leaf-700', 'info' => 'border-brand-100 bg-brand-50 text-brand-700'];
  $maxMutual = max(1, ...array_values($d['byMutual'] ?: [0]));
  $maxAct = max(1, ...array_values($d['byAct'] ?: [0]));
@endphp
<x-layouts.provider title="{{ __('Tableau de bord') }}">
  <x-tenant.hero icon="chart" kicker="{{ __('Tableau de bord') }}" tone="teal">
    <x-slot:title>{{ $provider->name }}</x-slot:title>
    <x-slot:subtitle>{{ __(\App\Models\Central\CareProvider::TYPES[$provider->type] ?? '') }}@if($provider->locality) · {{ $provider->locality }}@endif — {{ trans_choice(':count mutuelle conventionnée|:count mutuelles conventionnées', count($d['mutuals']), ['count' => count($d['mutuals'])]) }}. {{ __('Chiffres réels lus dans chaque mutuelle (actualisés toutes les 2 minutes).') }}</x-slot:subtitle>
    <x-slot:actions>
      @if ($hasMutuals && ProviderAccess::allowed('rights.verify'))<a href="{{ route('pro.go', 'verify') }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-leaf-500/25"><x-tenant.icon name="shield" :size="18"/> {{ __('Vérifier les droits') }}</a>@endif
    </x-slot:actions>
  </x-tenant.hero>

  @if (! $hasMutuals)
    <x-tenant.panel class="mt-6 p-6">
      <h2 class="text-lg font-bold text-[#0B1F3A]">{{ __('Aucune convention en place pour l’instant') }}</h2>
      <p class="mt-1 text-sm text-ink/60">{{ __('Le tableau de bord se remplit dès qu’une mutuelle a accepté votre établissement.') }} @if($requests->isNotEmpty()){{ __('Demande(s) en attente :') }} <b>{{ $requests->pluck('mutual.name')->filter()->implode(', ') }}</b>.@endif</p>
      @if (ProviderAccess::allowed('affiliation.request'))<a href="{{ route('pro.mutuals') }}" class="mt-4 inline-block rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">{{ __('Demander une affiliation à une mutuelle') }}</a>@endif
    </x-tenant.panel>
  @endif

  @if ($d['errors'])
    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ __('Données momentanément indisponibles pour : :list. Les autres chiffres sont à jour.', ['list' => implode(', ', $d['errors'])]) }}</div>
  @endif

  {{-- Activité (nombres : additionnés entre mutuelles) --}}
  <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
    <x-tenant.kpi label="{{ __('Patients identifiés aujourd’hui') }}" :value="$n($c['patients_today'])" icon="users" tone="brand" hint="{{ __('Droits vérifiés, adhérents distincts') }}"/>
    <x-tenant.kpi label="{{ __('Patients identifiés ce mois') }}" :value="$n($c['patients_month'])" icon="users" tone="brand"/>
    <x-tenant.kpi label="{{ __('Prises en charge en attente') }}" :value="$n($c['pending'])" icon="clipboard" tone="amber" hint="{{ __('À valider par la mutuelle') }}"/>
    <x-tenant.kpi label="{{ __('Prises en charge validées') }}" :value="$n($c['validated'])" icon="check" tone="leaf"/>
    <x-tenant.kpi label="{{ __('Prises en charge rejetées') }}" :value="$n($c['rejected'])" icon="alert" tone="red"/>
    <x-tenant.kpi label="{{ __('Consultations réalisées') }}" :value="$n($c['consultations'])" icon="stethoscope" tone="teal" hint="{{ __('Actes de la catégorie consultation') }}"/>
    <x-tenant.kpi label="{{ __('Factures transmises') }}" :value="$n($c['inv_total'])" icon="send" tone="violet"/>
    <x-tenant.kpi label="{{ __('Factures acceptées') }}" :value="$n($c['inv_accepted'])" icon="check" tone="leaf" hint="{{ __('En traitement, en paiement ou payées') }}"/>
    <x-tenant.kpi label="{{ __('Factures rejetées') }}" :value="$n($c['inv_rejected'])" icon="alert" tone="red"/>
    <x-tenant.kpi label="{{ __('Délai moyen de paiement') }}" :value="$d['avgDelay'] === null ? '—' : ($d['avgDelay'] < 1 ? __('< 1 jour') : __(':n j', ['n' => str_replace('.', ',', $d['avgDelay'])]))" icon="history" tone="brand" :hint="$d['avgDelay'] !== null ? __('De la demande au paiement enregistré') : __('Aucune facture payée')"/>
  </div>

  {{-- Montants : par devise, jamais convertis --}}
  @if ($showMoney)
    @foreach ($d['money'] as $code => $mm)
      <x-tenant.panel class="mt-6" :title="__('Montants en :currency (:code)', ['currency' => $mm['currency']?->name ?? $code, 'code' => $code])" icon="wallet">
        <div class="grid grid-cols-2 gap-3 p-5 sm:px-6 md:grid-cols-3 lg:grid-cols-6">
          @foreach (['tobill' => [__('À facturer'), __('Soins validés non encore facturés')], 'billed' => [__('Total facturé'), null], 'accepted' => [__('Accepté par la mutuelle'), null], 'paid' => [__('Payé'), __('Paiements enregistrés par la mutuelle')], 'due' => [__('Restant dû'), __('Facturé, non payé, non rejeté')], 'rejected' => [__('Rejeté'), null]] as $k => [$lbl, $hint])
            <div class="rounded-2xl bg-paper p-4 ring-1 ring-ink/[.05]">
              <div class="text-lg font-extrabold tabular-nums text-[#0B1F3A]">{{ $mm['currency'] ? $mm['currency']->format((int) $mm[$k]) : $n($mm[$k]) }}</div>
              <div class="mt-0.5 text-xs font-medium text-ink/55">{{ $lbl }}</div>
              @if ($hint)<div class="text-[11px] text-ink/40">{{ $hint }}</div>@endif
            </div>
          @endforeach
        </div>
        <p class="px-5 pb-5 text-xs text-ink/50 sm:px-6">{{ __('Une facture acceptée n’est pas une facture payée : « Payé » ne compte que les paiements enregistrés par le trésorier de la mutuelle. MX-CONNECT ne détient aucun fonds.') }}@if ($d['rejectRate'] !== null) {{ __('Taux de rejet des factures :') }} <b>{{ str_replace('.', ',', $d['rejectRate']) }} %</b>.@endif</p>
      </x-tenant.panel>
    @endforeach
  @endif

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="min-w-0 space-y-6 lg:col-span-2">
      <x-tenant.panel title="{{ __('Évolution mensuelle') }}" icon="chart">
        <div class="p-5 sm:px-6">
          <p class="text-xs text-ink/55">{{ __('Prises en charge (hors rejetées, par date des soins) et factures transmises, sur 12 mois. Survolez un mois pour ses valeurs.') }}</p>
          <x-tenant.month-chart class="mt-3" :months="$countMonths" :currency="new \App\Support\CountUnit()"
            :series="['care' => [__('Prises en charge'), '#0A5FB5'], 'invoices' => [__('Factures transmises'), '#D97706']]" />
        </div>
      </x-tenant.panel>

      @if ($showMoney)
        @foreach ($d['paidSeries'] as $code => $series)
          @php $cur = $d['money'][$code]['currency'] ?? null; @endphp
          @if ($cur && array_sum($series) > 0)
            <x-tenant.panel :title="__('Paiements reçus par mois (:code)', ['code' => $code])" icon="wallet">
              <div class="p-5 sm:px-6">
                <x-tenant.month-chart :months="collect($d['months'])->map(fn ($ym) => ['label' => $label($ym), 'paid' => $series[$ym] ?? 0])->all()" :currency="$cur"
                  :series="['paid' => [__('Paiements enregistrés par les mutuelles'), '#2E9E4F']]" />
              </div>
            </x-tenant.panel>
          @endif
        @endforeach
      @endif

      <div class="grid gap-6 sm:grid-cols-2">
        <x-tenant.panel title="{{ __('Par mutuelle') }}" icon="pie">
          <div class="space-y-3 p-5 sm:px-6">
            @forelse ($d['byMutual'] as $name => $v)
              <div><div class="flex justify-between gap-2 text-sm"><span class="truncate">{{ $name }}</span><b class="tabular-nums">{{ $n($v) }}</b></div>
                <div class="mt-1 h-2 rounded-full bg-mist"><div class="h-2 rounded-full bg-brand-500" style="width: {{ round(100 * $v / $maxMutual) }}%"></div></div></div>
            @empty
              <p class="text-sm text-ink/50">{{ __('Aucune prise en charge pour l’instant.') }}</p>
            @endforelse
            <p class="text-[11px] text-ink/40">{{ __('Prises en charge hors rejetées.') }}</p>
          </div>
        </x-tenant.panel>
        <x-tenant.panel title="{{ __('Actes les plus fréquents') }}" icon="stethoscope">
          <div class="space-y-3 p-5 sm:px-6">
            @forelse ($d['byAct'] as $name => $v)
              <div><div class="flex justify-between gap-2 text-sm"><span class="truncate">{{ $name }}</span><b class="tabular-nums">{{ $n($v) }}</b></div>
                <div class="mt-1 h-2 rounded-full bg-mist"><div class="h-2 rounded-full bg-leaf-500" style="width: {{ round(100 * $v / $maxAct) }}%"></div></div></div>
            @empty
              <p class="text-sm text-ink/50">{{ __('Aucun acte déclaré pour l’instant.') }}</p>
            @endforelse
          </div>
        </x-tenant.panel>
      </div>
    </div>

    <div class="min-w-0 space-y-6">
      <x-tenant.panel title="{{ __('Alertes') }}" icon="bell">
        <ul class="space-y-2.5 p-5 sm:px-6">
          @forelse ($d['alerts'] as $a)
            <li class="rounded-xl border px-3 py-2 text-sm {{ $tones[$a['level']] ?? $tones['info'] }}">
              @if (! empty($a['mutual']))<b>{{ $a['mutual'] }}</b> — @endif{{ $a['text'] }}
              @if (! empty($a['amount']) && $showMoney && ! empty($a['currency']))<span class="block text-xs">{{ __('Montant : :amount', ['amount' => $a['currency']->format((int) $a['amount'])]) }}</span>@endif
            </li>
          @empty
            <li class="text-sm text-ink/50">{{ __('Aucune alerte. Les échéances de convention et d’agrément, les rejets et les paiements reçus apparaîtront ici.') }}</li>
          @endforelse
        </ul>
      </x-tenant.panel>

      @if ($hasMutuals)
        <x-tenant.panel title="{{ __('Mes mutuelles') }}" icon="globe">
          <ul class="divide-y divide-ink/5 px-5 pb-4 sm:px-6">
            @foreach ($d['mutuals'] as $row)
              @php $mc = $row['data']['counts']; @endphp
              <li class="py-3 text-sm">
                <div class="flex items-center justify-between gap-2"><b class="truncate">{{ $row['mutual']->name }}</b>
                  @if (ProviderAccess::allowed('conventions.view'))<a href="{{ route('pro.mutuals.show', $row['mutual']->id) }}" class="shrink-0 text-xs text-brand-700 hover:underline">{{ __('Convention') }}</a>@endif</div>
                <div class="mt-1 text-xs text-ink/55">{{ __(':pending en attente · :validated validée(s) · :inv_total facture(s)', ['pending' => $mc['pending'], 'validated' => $mc['validated'], 'inv_total' => $mc['inv_total']]) }}</div>
                @if (ProviderAccess::allowed('rights.verify'))
                  <form method="POST" action="{{ route('pro.open', ['verify', $row['mutual']->id]) }}" class="mt-2">@csrf
                    <button class="rounded-md border border-ink/15 px-3 py-1.5 text-xs font-semibold text-pine hover:bg-mist">{{ __('Ouvrir le portail de la mutuelle') }}</button></form>
                @endif
              </li>
            @endforeach
          </ul>
        </x-tenant.panel>
      @endif
    </div>
  </div>
</x-layouts.provider>
