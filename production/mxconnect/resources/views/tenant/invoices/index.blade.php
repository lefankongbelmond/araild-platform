@php
  $S = \App\Models\Tenant\ProviderInvoice::STATUSES;
  $tabs = ['a-payer' => 'À payer', 'payees' => 'Payées', 'rejetees' => 'Rejetées'];
  $f = 'rounded-md border border-ink/15 bg-white px-3 py-2 text-sm';
  $me = auth()->id();
@endphp
<x-layouts.tenant title="Factures des prestataires">
  <x-tenant.hero icon="receipt" kicker="Facturation et finances" tone="teal">
    <x-slot:title>Factures des prestataires</x-slot:title>
    <x-slot:subtitle>Toutes les demandes de paiement des établissements conventionnés (tiers payant), au même endroit. Le paiement se fait hors de la plateforme ; MX-CONNECT l’enregistre en trésorerie.</x-slot:subtitle>
    <x-slot:actions>
      @acces('invoices.export')<a href="{{ route('invoices.export', request()->query()) }}" class="rounded-md border border-ink/10 bg-white px-4 py-2 text-sm font-medium text-ink/70 hover:bg-mist">Exporter (CSV)</a>@endacces
    </x-slot:actions>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid gap-3 sm:grid-cols-3">
    @foreach ($tabs as $k => $label)
      <a href="{{ route('invoices.index', ['statut' => $k] + request()->only('prestataire', 'du', 'au')) }}" class="rounded-lg border bg-white p-4 {{ $statut === $k ? 'border-pine' : 'border-ink/10' }}">
        <div class="text-xs font-semibold uppercase tracking-wide text-ink/50">{{ $label }}</div>
        <div class="mt-1 text-xl font-semibold tabular-nums">{{ $currency->format($totals[$k][1]) }}</div>
        <div class="text-xs text-ink/55">{{ $totals[$k][0] }} facture(s)</div>
      </a>
    @endforeach
  </div>

  <form method="GET" class="mt-4 flex flex-wrap items-end gap-2">
    <input type="hidden" name="statut" value="{{ $statut }}">
    <label class="text-xs text-ink/60">Prestataire<select name="prestataire" class="{{ $f }} mt-1 block"><option value="">Tous</option>@foreach ($providers as $id => $n)<option value="{{ $id }}" @selected((int) request('prestataire') === (int) $id)>{{ $n }}</option>@endforeach</select></label>
    <label class="text-xs text-ink/60">Du<input type="date" name="du" value="{{ request('du') }}" class="{{ $f }} mt-1 block"></label>
    <label class="text-xs text-ink/60">Au<input type="date" name="au" value="{{ request('au') }}" class="{{ $f }} mt-1 block"></label>
    <button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white">Filtrer</button>
  </form>

  <div class="mt-4 space-y-3">
    @forelse ($invoices as $inv)
      @php $m = $inv->careClaim?->member; @endphp
      <article class="rounded-lg border border-ink/10 bg-white p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0 text-sm">
            <div class="flex flex-wrap items-center gap-2"><b>{{ $providers[$inv->provider_id] ?? 'Prestataire n° ' . $inv->provider_id }}</b>
              <span class="rounded-full px-2 py-0.5 text-xs {{ $inv->status === 'paid' ? 'bg-mist text-pine' : ($inv->status === 'rejected' ? 'bg-clay/10 text-clay' : 'bg-amber-50 text-amber-700') }}">{{ $S[$inv->status] ?? $inv->status }}</span></div>
            <div class="mt-1 text-ink/60">Facture n° {{ $inv->id }}@if ($inv->provider_reference) · réf. {{ $inv->provider_reference }}@endif · reçue le {{ $inv->created_at?->format('d/m/Y') }}
              @if ($m) · {{ $m->last_name }} {{ $m->first_name }} <span class="font-mono text-xs">{{ $m->member_code }}</span>@endif</div>
            @if ($inv->decided_at)<div class="text-xs text-ink/55">{{ $inv->status === 'paid' ? 'Payée' : 'Décision' }} le {{ $inv->decided_at->format('d/m/Y') }}</div>@endif
            @if ($inv->decision_note)<div class="mt-1 text-sm text-clay">Motif : {{ $inv->decision_note }}</div>@endif
            <div class="mt-2 flex flex-wrap gap-3 text-xs">
              @if ($canClaim && $inv->care_claim_id)<a class="text-pine underline" href="{{ route('claims.show', $inv->care_claim_id) }}">Voir le recours</a>@endif
              @if ($inv->supporting_doc_path)
                @if ($canDoc && $inv->care_claim_id)<a class="text-pine underline" href="{{ route('claims.invoice.document', [$inv->care_claim_id, $inv->id]) }}">Facture PDF</a>
                @else<span class="text-ink/45">Facture PDF reçue</span>@endif
              @endif
            </div>
          </div>
          <div class="text-right">
            <div class="text-lg font-semibold tabular-nums">{{ $currency->format($inv->amount_minor) }}</div>
            @if (in_array($inv->status, \App\Models\Tenant\ProviderInvoice::PAYABLE, true))
              <div class="mt-2 flex flex-wrap justify-end gap-2">
                @if ($canPay && $inv->care_claim_id && (int) $inv->captured_by !== (int) $me)
                  <form method="POST" action="{{ route('claims.invoice.pay', [$inv->care_claim_id, $inv->id]) }}" onsubmit="return confirm('Enregistrer le paiement de {{ $currency->format($inv->amount_minor) }} ? (paiement fait hors de la plateforme)')">@csrf
                    <button class="rounded-md bg-pine px-3 py-1.5 text-sm font-medium text-white">Enregistrer le paiement</button></form>
                @endif
              </div>
            @endif
          </div>
        </div>
        @if ($canReject && in_array($inv->status, \App\Models\Tenant\ProviderInvoice::PAYABLE, true) && (int) $inv->captured_by !== (int) $me)
          <details class="mt-3 border-t border-ink/10 pt-3">
            <summary class="cursor-pointer text-sm text-clay">Rejeter cette facture…</summary>
            <form method="POST" action="{{ route('invoices.reject', $inv->id) }}" class="mt-2 flex flex-wrap items-end gap-2">@csrf
              <label class="min-w-0 flex-1 text-xs text-ink/60">Motif (visible du prestataire)<input name="note" required minlength="5" maxlength="500" class="{{ $f }} mt-1 block w-full" placeholder="Ex. montant différent de la prestation validée"></label>
              <button class="rounded-md bg-clay px-3 py-2 text-sm font-medium text-white">Rejeter</button>
            </form>
          </details>
        @endif
      </article>
    @empty
      <div class="rounded-lg border border-ink/10 bg-white p-10 text-center text-sm text-ink/50">Aucune facture dans cette catégorie.</div>
    @endforelse
  </div>
  <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.tenant>
