@php
  $S = ['captured' => 'En contrôle à la mutuelle', 'processed' => 'Validée, en attente de paiement', 'prepared' => 'Mise en paiement', 'paid' => 'Payée', 'rejected' => 'Rejetée'];
  $L = \App\Models\Tenant\ProviderInvoiceLine::STATUSES;
  $E = \App\Models\Tenant\InvoiceEvent::LABELS;
  $who = fn ($p) => $p?->careClaim?->dependent ? trim($p->careClaim->dependent->last_name . ' ' . $p->careClaim->dependent->first_name) : trim(($p?->careClaim?->member?->last_name ?? '') . ' ' . ($p?->careClaim?->member?->first_name ?? ''));
@endphp
<x-layouts.tenant title="Facture {{ $inv->label() }}">
  <div class="print:hidden">
    @include('tenant.portal._head', ['title' => 'Facture ' . $inv->label()])
    <div class="mt-4 flex flex-wrap gap-2 text-sm">
      <a href="{{ route('portal.invoices') }}" class="rounded-md border border-ink/10 bg-white px-3 py-1.5 text-ink/70 hover:bg-mist">← Mes factures</a>
      <button type="button" onclick="window.print()" class="rounded-md border border-ink/10 bg-white px-3 py-1.5 text-ink/70 hover:bg-mist">Imprimer le bordereau (ou PDF)</button>
      @if ($inv->supporting_doc_path)<a class="rounded-md border border-ink/10 bg-white px-3 py-1.5 text-pine" href="{{ route('portal.invoices.document', $inv->id) }}">Facture PDF jointe</a>@endif
    </div>
  </div>

  {{-- Bordereau imprimable (le navigateur l'enregistre en PDF). --}}
  <section class="mt-6 rounded-lg border border-ink/10 bg-white p-5 text-sm">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <div class="text-xs font-semibold uppercase tracking-wide text-ink/50">Bordereau de facturation (tiers payant)</div>
        <h2 class="mt-1 text-lg font-semibold">{{ $inv->label() }}</h2>
        <div class="text-ink/60">{{ $provider->name }} → {{ tenant('name') }}</div>
        <div class="text-xs text-ink/55">Envoyée le {{ $inv->created_at?->format('d/m/Y') }}@if ($inv->provider_reference) · votre réf. {{ $inv->provider_reference }}@endif</div>
      </div>
      <div class="text-right">
        <div class="text-xs text-ink/55">{{ $inv->status === 'captured' ? 'Montant demandé' : 'Montant validé' }}</div>
        <div class="text-xl font-semibold tabular-nums">{{ $currency->format($inv->amount_minor) }}</div>
        @if ($inv->billed_minor && $inv->billed_minor !== $inv->amount_minor)<div class="text-xs text-ink/55">demandé : {{ $currency->format($inv->billed_minor) }}</div>@endif
        <div class="mt-1 text-xs font-semibold {{ $inv->status === 'paid' ? 'text-pine' : ($inv->status === 'rejected' ? 'text-clay' : 'text-ink/60') }}">{{ $S[$inv->status] ?? $inv->status }}</div>
      </div>
    </div>
    @if ($inv->status === 'paid')
      <p class="mt-3 rounded-md bg-mist px-3 py-2">Payée le {{ ($inv->paid_on ?? $inv->decided_at)?->format('d/m/Y') }}@if ($inv->payment_mode) par {{ mb_strtolower(\App\Services\Tenant\ProviderBillingService::MODES[$inv->payment_mode] ?? $inv->payment_mode) }}@endif @if ($inv->payment_reference) — référence {{ $inv->payment_reference }}@endif.</p>
    @elseif ($inv->status === 'rejected' && $inv->decision_note)
      <p class="mt-3 rounded-md border border-clay/30 bg-clay/5 px-3 py-2 text-clay">Motif : {{ $inv->decision_note }}</p>
    @endif

    @if ($lines->isNotEmpty())
      <ul class="mt-4 divide-y divide-ink/5 border-t border-b border-ink/10">
        @foreach ($lines as $l)
          <li class="flex flex-wrap items-start justify-between gap-2 py-2">
            <div class="min-w-0">
              <div><b>{{ $l->care_date?->format('d/m/Y') }}</b> · {{ $who($l->prestation) }} <span class="font-mono text-xs text-ink/50">{{ $l->prestation?->careClaim?->member?->member_code }}</span></div>
              <div class="text-xs text-ink/55">Prestation n° {{ $l->prestation_id }}@if ($l->prestation?->actType) · {{ $l->prestation->actType->label }}@endif</div>
              @if ($l->status === 'rejected' && $l->decision_note)<div class="mt-1 text-xs text-clay">Motif : {{ $l->decision_note }}</div><div class="text-xs text-ink/50 print:hidden">À corriger : elle revient dans « à facturer ».</div>@endif
            </div>
            <div class="text-right">
              <div class="tabular-nums">{{ $currency->format($l->amount_minor) }}</div>
              <div class="text-xs {{ $l->status === 'accepted' ? 'text-pine' : ($l->status === 'rejected' ? 'text-clay' : 'text-ink/60') }}">{{ $L[$l->status] ?? $l->status }}</div>
            </div>
          </li>
        @endforeach
      </ul>
      <div class="flex flex-wrap justify-between gap-2 py-2 font-medium"><span>{{ $lines->count() }} prestation(s)</span><span class="tabular-nums">{{ $currency->format($lines->sum('amount_minor')) }}</span></div>
    @else
      <p class="mt-4 text-ink/55">Facture d’une prise en charge, envoyée avant la facturation à lignes.@if ($inv->careClaim?->member) Patient : {{ $inv->careClaim->member->last_name }} {{ $inv->careClaim->member->first_name }}.@endif</p>
    @endif
  </section>

  @if ($events->isNotEmpty())
    <section class="mt-6 rounded-lg border border-ink/10 bg-white p-5 text-sm print:hidden">
      <h2 class="font-semibold">Historique</h2>
      <ul class="mt-2 divide-y divide-ink/5">
        @foreach ($events as $e)
          <li class="py-2"><span class="text-xs text-ink/50">{{ $e->created_at?->format('d/m/Y H:i') }}</span> — {{ $E[$e->event] ?? $e->event }}@if ($e->amount_minor !== null && $e->event !== 'document') · {{ $currency->format($e->amount_minor) }}@endif
            @if ($e->note && $e->event !== 'rejected')<span class="text-ink/55"> · {{ $e->note }}</span>@endif</li>
        @endforeach
      </ul>
    </section>
  @endif
</x-layouts.tenant>
