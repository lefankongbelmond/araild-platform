<x-layouts.tenant title="Pharmacie">
  @php $S = ['captured' => 'En attente', 'controlled' => 'Contrôlée', 'validated' => 'Validée', 'rejected' => 'Rejetée']; @endphp
  <x-tenant.hero icon="pill" kicker="Prestations · pharmacie" tone="violet">
    <x-slot:title>Délivrances en pharmacie</x-slot:title>
    <x-slot:subtitle>Ordonnances délivrées par les pharmacies conventionnées : médicaments, quantités, lignes non prises en charge. Une même ordonnance ne peut pas être facturée deux fois ; les prix au-dessus du prix de référence et les délivrances rapprochées créent des alertes à examiner.</x-slot:subtitle>
    <x-slot:actions>
      @acces('pharmacy.policy')<a href="{{ route('pharmacy.policy') }}" class="inline-flex h-10 items-center rounded-xl border border-ink/10 bg-white px-4 text-sm font-semibold text-[#0B1F3A] hover:bg-paper">Politique pharmaceutique</a>@endacces
    </x-slot:actions>
  </x-tenant.hero>

  <form method="GET" class="mt-6 grid gap-2 sm:grid-cols-3">
    <input name="q" value="{{ $q }}" placeholder="N° d’ordonnance, n° d’adhérent ou nom" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
    <button class="rounded-md border border-pine px-4 py-2 text-sm font-semibold text-pine hover:bg-mist">Rechercher</button>
  </form>

  <div class="mt-4 space-y-3">
    @forelse ($items as $d)
      @php $rx = $d->prescription; $who = $rx?->dependent ?? $rx?->member; @endphp
      <article class="rounded-lg border border-ink/10 bg-white p-4 text-sm">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div class="min-w-0">
            <b>Ordonnance {{ $rx?->reference }}</b> <span class="text-xs text-ink/55">du {{ $rx?->prescribed_on?->format('d/m/Y') }} · {{ $rx?->prescriber }}@if ($rx?->renewals_allowed) · {{ $rx->renewals_allowed }} renouvellement(s)@endif</span>
            <div class="text-xs text-ink/60">{{ $who?->last_name }} {{ $who?->first_name }} ({{ $rx?->member?->member_code }}) · délivrée le {{ $d->dispensed_on?->format('d/m/Y') }} par {{ $providers[$d->provider_id] ?? 'établissement n° ' . $d->provider_id }}</div>
          </div>
          <div class="text-right text-xs">
            <div class="tabular-nums"><b>{{ $currency->format($d->total_minor) }}</b>@if ($d->excluded_minor) · dont {{ $currency->format($d->excluded_minor) }} non pris en charge @endif</div>
            @if ($d->prestation)<a href="{{ route('claims.show', $d->prestation->care_claim_id) }}" class="text-pine hover:underline">Prise en charge n° {{ $d->prestation_id }} · {{ $S[$d->prestation->status] ?? $d->prestation->status }}</a>@endif
          </div>
        </div>
        <ul class="mt-2 space-y-1 text-xs text-ink/70">
          @foreach ($d->lines as $l)
            <li class="flex flex-wrap justify-between gap-2"><span>{{ $l->medicine?->label }} — {{ $l->quantity_dispensed }} / {{ $l->quantity_prescribed }} prescrit(s)@if (! $l->eligible) · <b class="text-clay">{{ \App\Models\Tenant\DispensationLine::REASONS[$l->reason] ?? 'non pris en charge' }}</b>@endif @if ($l->above_reference) · <b class="text-amber-700">au-dessus du prix de référence</b>@endif</span><span class="tabular-nums">{{ $currency->format($l->amount_minor) }}</span></li>
          @endforeach
        </ul>
      </article>
    @empty
      <p class="rounded-lg border border-ink/10 bg-white p-8 text-center text-sm text-ink/50">Aucune délivrance enregistrée.</p>
    @endforelse
  </div>
  <div class="mt-4">{{ $items->links() }}</div>
</x-layouts.tenant>
