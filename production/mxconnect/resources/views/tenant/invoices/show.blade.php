@php
  $S = array_map('__', \App\Models\Tenant\ProviderInvoice::STATUSES);
  $L = array_map('__', \App\Models\Tenant\ProviderInvoiceLine::STATUSES);
  $E = array_map('__', \App\Models\Tenant\InvoiceEvent::LABELS);
  $f = 'rounded-md border border-ink/15 bg-white px-3 py-2 text-sm';
  $who = fn ($p) => $p?->careClaim?->dependent ? trim($p->careClaim->dependent->last_name . ' ' . $p->careClaim->dependent->first_name) . ' (ayant droit)' : trim(($p?->careClaim?->member?->last_name ?? '') . ' ' . ($p?->careClaim?->member?->first_name ?? ''));
  $pending = $lines->where('status', 'pending');
  $decide = $canDecide && ! $mine && $inv->status === 'captured' && $pending->isNotEmpty();
@endphp
<x-layouts.tenant title="Facture {{ $inv->label() }}">
  <x-tenant.hero icon="receipt" kicker="{{ __('Factures des prestataires') }}" tone="teal">
    <x-slot:title>{{ __('Facture :label', ['label' => $inv->label()]) }}</x-slot:title>
    <x-slot:subtitle>{{ $provider?->name ?? __('Prestataire n° :id', ['id' => $inv->provider_id]) }} · {{ __('reçue le :date', ['date' => $inv->created_at?->format('d/m/Y')]) }}@if ($inv->provider_reference) · {{ __('réf. :reference', ['reference' => $inv->provider_reference]) }}@endif · {{ $S[$inv->status] ?? $inv->status }}</x-slot:subtitle>
    <x-slot:actions>
      <a href="{{ route('invoices.index') }}" class="rounded-md border border-ink/10 bg-white px-4 py-2 text-sm font-medium text-ink/70 hover:bg-mist">{{ __('← Registre') }}</a>
      @if ($inv->supporting_doc_path && $canDoc)<a href="{{ route('invoices.document', $inv->id) }}" class="rounded-md border border-ink/10 bg-white px-4 py-2 text-sm font-medium text-pine hover:bg-mist">{{ __('Facture PDF') }}</a>@endif
    </x-slot:actions>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid items-start gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
      <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="font-semibold">{{ __('Lignes de la facture') }}</h2>
          @if ($decide && $pending->count() > 1)
            <form method="POST" action="{{ route('invoices.lines.decide', $inv->id) }}" onsubmit="return window.confirm('Accepter les {{ $pending->count() }} lignes en attente ?')">@csrf
              <input type="hidden" name="decision" value="accept_all"><button class="rounded-md border border-pine px-3 py-1.5 text-sm font-medium text-pine">{{ __('Accepter les :count lignes en attente', ['count' => $pending->count()]) }}</button></form>
          @endif
        </div>
        @if ($mine && $inv->status === 'captured')<p class="mt-2 text-xs text-clay">{{ __('Vous avez saisi cette facture : une autre personne doit la contrôler (contrôle « 4 yeux »).') }}</p>@endif
        @if ($lines->isEmpty())
          <p class="mt-3 text-ink/55">{{ __('Facture d’une prise en charge, sans détail par ligne (envoyée avant la facturation à lignes). Elle se paie directement.') }}
            @if ($canClaim && $inv->care_claim_id)<a class="text-pine underline" href="{{ route('claims.show', $inv->care_claim_id) }}">{{ __('Voir le recours') }}</a>@endif</p>
        @else
          <ul class="mt-3 divide-y divide-ink/5">
            @foreach ($lines as $l)
              <li class="py-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div class="min-w-0">
                    <div><b>{{ $l->care_date?->format('d/m/Y') }}</b> {{ __('· prestation n° :prestation_id · :prestation', ['prestation_id' => $l->prestation_id, 'prestation' => $who($l->prestation)]) }} <span class="font-mono text-xs text-ink/55">{{ $l->prestation?->careClaim?->member?->member_code }}</span></div>
                    @if ($health && $l->prestation?->actType)<div class="text-xs text-ink/55">{{ $l->prestation->actType->label }}</div>@endif
                    @if ($canClaim && $l->care_claim_id)<a class="text-xs text-pine underline" href="{{ route('claims.show', $l->care_claim_id) }}">{{ __('Voir le recours') }}</a>@endif
                  </div>
                  <div class="text-right">
                    <div class="font-medium tabular-nums">{{ $currency->format($l->amount_minor) }}</div>
                    <div class="text-xs {{ $l->status === 'accepted' ? 'text-pine' : ($l->status === 'rejected' ? 'text-clay' : 'text-amber-700') }}">{{ $L[$l->status] ?? $l->status }}@if ($l->decided_by) · {{ $staff[$l->decided_by] ?? '' }}@endif</div>
                  </div>
                </div>
                @if ($l->status === 'rejected' && $l->decision_note)<div class="mt-1 text-xs text-clay">{{ __('Motif : :decision_note', ['decision_note' => $l->decision_note]) }}</div>@endif
                @if ($decide && $l->status === 'pending')
                  <div class="mt-2 flex flex-wrap items-end gap-2">
                    <form method="POST" action="{{ route('invoices.lines.decide', $inv->id) }}">@csrf
                      <input type="hidden" name="line" value="{{ $l->id }}"><input type="hidden" name="decision" value="accepted">
                      <button class="rounded-md bg-pine px-3 py-1.5 text-sm font-medium text-white">{{ __('Accepter') }}</button></form>
                    <form method="POST" action="{{ route('invoices.lines.decide', $inv->id) }}" class="flex min-w-0 flex-1 flex-wrap items-end gap-2">@csrf
                      <input type="hidden" name="line" value="{{ $l->id }}"><input type="hidden" name="decision" value="rejected">
                      <label class="min-w-0 flex-1 text-xs text-ink/60">{{ __('Motif du rejet (visible de l’établissement)') }}<input name="note" required minlength="5" maxlength="500" class="{{ $f }} mt-1 block w-full" placeholder="{{ __('Ex. montant différent du tarif conventionnel') }}"></label>
                      <button class="rounded-md border border-clay px-3 py-2 text-sm font-medium text-clay">{{ __('Rejeter la ligne') }}</button></form>
                  </div>
                @endif
              </li>
            @endforeach
          </ul>
          <div class="mt-3 border-t border-ink/10 pt-3 text-sm">
            {{ __('Demandé :') }} <b class="tabular-nums">{{ $currency->format($inv->billed_minor ?? $lines->sum('amount_minor')) }}</b>
            {{ __('· accepté :') }} <b class="tabular-nums text-pine">{{ $currency->format($lines->where('status', 'accepted')->sum('amount_minor')) }}</b>
            {{ __('· rejeté :') }} <b class="tabular-nums text-clay">{{ $currency->format($lines->where('status', 'rejected')->sum('amount_minor')) }}</b>
            @if ($pending->isNotEmpty()) · {{ __('en attente :') }} <b class="tabular-nums">{{ __(':count ligne(s)', ['count' => $pending->count()]) }}</b>@endif
          </div>
        @endif
      </section>

      @if ($events->isNotEmpty())
        <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
          <h2 class="font-semibold">{{ __('Historique') }}</h2>
          <ul class="mt-2 divide-y divide-ink/5">
            @foreach ($events as $e)
              <li class="py-2"><span class="text-xs text-ink/50">{{ $e->created_at?->format('d/m/Y H:i') }}</span> — {{ $E[$e->event] ?? $e->event }}@if ($e->amount_minor !== null && $e->event !== 'document') · {{ $currency->format($e->amount_minor) }}@endif
                @if ($e->note)<span class="text-ink/55"> · {{ $e->note }}</span>@endif
                @if ($e->user_id && isset($staff[$e->user_id]))<span class="text-xs text-ink/45"> ({{ $staff[$e->user_id] }})</span>@endif</li>
            @endforeach
          </ul>
        </section>
      @endif
    </div>

    <aside class="space-y-4">
      <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <div class="text-xs font-semibold uppercase tracking-wide text-ink/50">{{ match (true) { $inv->status === 'paid' => 'Montant payé', $inv->status === 'rejected' => 'Montant', $inv->status === 'captured' && $lines->isNotEmpty() => 'Montant demandé', default => 'Montant à payer' } }}</div>
        <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $currency->format($inv->amount_minor) }}</div>
        <div class="mt-1 text-xs text-ink/55">{{ $S[$inv->status] ?? $inv->status }}</div>
        @if ($inv->checked_at)<div class="mt-2 text-xs text-ink/55">{{ __('Contrôlée le :date', ['date' => $inv->checked_at->format('d/m/Y')]) }}@if ($inv->checked_by) {{ __('par :name', ['name' => $staff[$inv->checked_by] ?? '']) }}@endif</div>@endif
        @if ($inv->status === 'paid')
          <p class="mt-3 rounded-md bg-mist px-3 py-2">{{ __('Payée le :date', ['date' => ($inv->paid_on ?? $inv->decided_at)?->format('d/m/Y')]) }}@if ($inv->payment_mode) · {{ isset($modes[$inv->payment_mode]) ? __($modes[$inv->payment_mode]) : $inv->payment_mode }}@endif @if ($inv->payment_reference) · {{ __('réf. :reference', ['reference' => $inv->payment_reference]) }}@endif</p>
        @elseif ($inv->status === 'rejected' && $inv->decision_note)
          <p class="mt-3 text-clay">{{ __('Motif : :decision_note', ['decision_note' => $inv->decision_note]) }}</p>
        @endif

        @if ($canPay && in_array($inv->status, \App\Models\Tenant\ProviderInvoice::PAYABLE, true))
          <div class="mt-4">
            @if ($payBlocker)<p class="text-xs text-ink/55">{{ $payBlocker }}</p>
            @elseif ($mine || ($inv->checked_by && (int) $inv->checked_by === (int) auth()->id()))<p class="text-xs text-clay">{{ __('Vous avez saisi ou contrôlé cette facture : une autre personne enregistre le paiement.') }}</p>
            @else @include('tenant.invoices._pay', ['inv' => $inv]) @endif
          </div>
        @endif
      </section>

      @if ($canReject && ! $mine && in_array($inv->status, \App\Models\Tenant\ProviderInvoice::PAYABLE, true))
        <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
          <details>
            <summary class="cursor-pointer text-clay">{{ __('Rejeter toute la facture…') }}</summary>
            <p class="mt-2 text-xs text-ink/55">{{ __('Doublon, pièce illisible… Toutes les lignes sont rejetées ; l’établissement peut les renvoyer dans une nouvelle facture.') }}</p>
            <form method="POST" action="{{ route('invoices.reject', $inv->id) }}" class="mt-2 grid gap-2">@csrf
              <label class="text-xs text-ink/60">{{ __('Motif (visible du prestataire)') }}<input name="note" required minlength="5" maxlength="500" class="{{ $f }} mt-1 block w-full"></label>
              <button class="rounded-md bg-clay px-3 py-2 text-sm font-medium text-white">{{ __('Rejeter la facture') }}</button>
            </form>
          </details>
        </section>
      @endif
    </aside>
  </div>
</x-layouts.tenant>
