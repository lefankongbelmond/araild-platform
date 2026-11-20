@php
  $S = ['captured' => __('En contrôle'), 'processed' => __('Validée, à payer'), 'prepared' => __('Mise en paiement'), 'paid' => __('Payée'), 'rejected' => __('Rejetée')];
  $who = fn ($p) => $p->careClaim?->dependent ? trim($p->careClaim->dependent->last_name . ' ' . $p->careClaim->dependent->first_name) : trim(($p->careClaim?->member?->last_name ?? '') . ' ' . ($p->careClaim?->member?->first_name ?? ''));
  $canBill = ($portal['bill'] ?? false) && $ready;
@endphp
<x-layouts.tenant title="{{ __('Mes factures') }}">
  @include('tenant.portal._head', ['title' => __('Mes factures')])

  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  {{-- Phase 5 (2026-11-26) : une facture = un bordereau de prestations validées, contrôlé ligne par ligne. --}}
  <section class="mt-6 rounded-lg border border-ink/10 bg-white p-5 text-sm">
    <h2 class="font-semibold">{{ __('Prestations validées à facturer') }}</h2>
    <p class="mt-1 text-xs text-ink/55">{{ __('Cochez les prestations à regrouper dans une même facture (bordereau). Montant = part de la mutuelle (tiers payant). La mutuelle contrôle chaque ligne, puis paie hors de la plateforme : une facture n’est « payée » qu’une fois le paiement effectué.') }}</p>
    @if ($billable->isEmpty())
      <p class="mt-3 text-ink/50">{{ __('Rien à facturer pour l’instant : les prestations apparaissent ici une fois validées par la mutuelle.') }}</p>
    @else
      <form method="POST" action="{{ route('portal.invoices.batch') }}" enctype="multipart/form-data" id="bordereau">@csrf
        @if ($canBill)<label class="mt-3 inline-flex items-center gap-2 text-xs text-ink/60"><input type="checkbox" checked data-all> {{ __('Tout cocher') }}</label>@endif
        <ul class="mt-2 divide-y divide-ink/5 rounded-md border border-ink/10">
          @foreach ($billable as $p)
            <li class="flex items-start gap-3 px-3 py-2">
              @if ($canBill)<input type="checkbox" name="prestations[]" value="{{ $p->id }}" data-amount="{{ (int) $p->mutual_part_minor }}" checked class="mt-1" aria-label="Facturer la prestation n° {{ $p->id }}">@endif
              <div class="min-w-0 flex-1">
                <div><b>{{ $p->care_date?->format('d/m/Y') }}</b> · {{ $who($p) }} <span class="font-mono text-xs text-ink/50">{{ $p->careClaim?->member?->member_code }}</span></div>
                <div class="text-xs text-ink/55">{{ __('Prestation n° :id', ['id' => $p->id]) }}@if ($p->actType) · {{ $p->actType->label }}@endif</div>
                @if (isset($resend[$p->id]))<div class="mt-1 text-xs text-clay">{{ __('Renvoi : rejetée sur la facture :reference', ['reference' => $resend[$p->id]['ref']]) }}@if ($resend[$p->id]['note']) — {{ $resend[$p->id]['note'] }}@endif</div>@endif
              </div>
              <div class="whitespace-nowrap text-right font-medium tabular-nums">{{ $currency->format((int) $p->mutual_part_minor) }}</div>
            </li>
          @endforeach
        </ul>
        @if ($canBill)
          <div class="mt-3 grid gap-3 sm:grid-cols-3">
            <div class="text-sm">{{ __('Total sélectionné') }}<div class="text-lg font-semibold tabular-nums" data-total>{{ $currency->format((int) $billable->sum('mutual_part_minor')) }}</div><div class="text-xs text-ink/50" data-count>{{ __(':count prestation(s)', ['count' => $billable->count()]) }}</div></div>
            <label class="text-xs text-ink/60">{{ __('Votre n° de facture (facultatif)') }}<input name="provider_reference" maxlength="60" value="{{ old('provider_reference') }}" class="mt-1 block w-full rounded-md border border-ink/15 px-3 py-2 text-sm"></label>
            <label class="min-w-0 text-xs text-ink/60">{{ __('Facture PDF (facultatif, 5 Mo)') }}<input type="file" name="document" accept="application/pdf,.pdf" class="mt-1 block w-full text-xs" style="max-width:100%"></label>
          </div>
          <button class="mt-3 rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">{{ __('Demander le paiement (envoyer la facture)') }}</button>
          <p class="mt-2 text-xs text-ink/50">{{ __('N’indiquez pas de diagnostic sur la facture si ce n’est pas indispensable.') }}</p>
        @endif
      </form>
      @if ($canBill)
      <script>
        (function () {
          var f = document.getElementById('bordereau'); if (!f) return;
          var boxes = f.querySelectorAll('input[name="prestations[]"]'), all = f.querySelector('[data-all]');
          // Même présentation que le serveur : le modèle « 0 FCFA » (ou équivalent) reçoit le nouveau montant.
          var unit = {{ (int) $currency->minor_unit }}, tpl = @json($currency->format(0));
          var fmt = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: unit, maximumFractionDigits: unit });
          function sum() { var t = 0, n = 0; boxes.forEach(function (b) { if (b.checked) { t += +b.dataset.amount; n++; } });
            f.querySelector('[data-total]').textContent = tpl.replace(/0(?:[.,]0+)?/, fmt.format(t / Math.pow(10, unit)).replace(/\u202f/g, '\u00a0')); f.querySelector('[data-count]').textContent = n + ' prestation(s)'; }
          boxes.forEach(function (b) { b.addEventListener('change', sum); });
          if (all) all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); sum(); });
        })();
      </script>
      @endif
    @endif
  </section>

  <div class="mt-6 flex flex-wrap items-center justify-between gap-2">
    <h2 class="font-semibold">{{ __('Mes factures') }}</h2>
    @if ($ready)<a href="{{ route('portal.invoices.export') }}" class="rounded-md border border-ink/10 bg-white px-3 py-1.5 text-sm text-ink/70 hover:bg-mist">{{ __('Exporter les lignes (CSV)') }}</a>@endif
  </div>
  <ul class="mt-3 divide-y divide-ink/5 rounded-lg border border-ink/10 bg-white text-sm">
    @forelse ($invoices as $inv)
      <li class="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
        <div class="min-w-0">
          <a class="font-medium text-pine underline" href="{{ route('portal.invoices.show', $inv->id) }}">{{ __('Facture :label', ['label' => $inv->label()]) }}</a>
          <div class="text-xs text-ink/55">{{ $inv->created_at?->format('d/m/Y') }}@if ($inv->provider_reference) · {{ __('votre réf. :reference', ['reference' => $inv->provider_reference]) }}@endif ·
            @if (($inv->lines_count ?? 0) > 0){{ $inv->lines_count }} prestation(s)@if ($inv->rejected_lines_count), <span class="text-clay">{{ __(':count rejetée(s)', ['count' => $inv->rejected_lines_count]) }}</span>@endif
            @else{{ $inv->careClaim?->member?->last_name }} {{ $inv->careClaim?->member?->first_name }}@endif</div>
          <div class="mt-1 text-xs {{ $inv->status === 'paid' ? 'text-pine' : ($inv->status === 'rejected' ? 'text-clay' : 'text-ink/60') }}">{{ $S[$inv->status] ?? $inv->status }}@if ($inv->status === 'paid') {{ __('le :date', ['date' => ($inv->paid_on ?? $inv->decided_at ?? $inv->updated_at)?->format('d/m/Y')]) }}@if ($inv->payment_reference) · {{ __('Réf. :reference', ['reference' => $inv->payment_reference]) }}@endif @endif</div>
          @if ($inv->status === 'rejected' && $inv->decision_note)<div class="mt-1 text-xs text-clay">{{ __('Motif : :decision_note', ['decision_note' => $inv->decision_note]) }}</div>@endif
          <div class="mt-1 text-xs">
            @if ($inv->supporting_doc_path)<a class="text-pine underline" href="{{ route('portal.invoices.document', $inv->id) }}">{{ __('Facture PDF') }}</a>@endif
            @if (in_array($inv->status, ['captured', 'processed'], true) && ($portal['bill'] ?? false))
              <form method="POST" action="{{ route('portal.invoices.document.store', $inv->id) }}" enctype="multipart/form-data" class="mt-1 flex flex-wrap items-center gap-2">@csrf
                <label class="sr-only" for="doc-{{ $inv->id }}">{{ __('Facture PDF') }}</label>
                <input id="doc-{{ $inv->id }}" type="file" name="document" accept="application/pdf,.pdf" required class="w-40 text-xs">
                <button class="text-pine underline">{{ $inv->supporting_doc_path ? __('Remplacer') : __('Joindre') }}</button>
              </form>
            @endif
          </div>
        </div>
        <div class="text-right font-medium tabular-nums">{{ $currency->format($inv->amount_minor) }}@if ($inv->billed_minor && $inv->billed_minor !== $inv->amount_minor)<div class="text-xs font-normal text-ink/50">{{ __('demandé :amount', ['amount' => $currency->format($inv->billed_minor)]) }}</div>@endif</div>
      </li>
    @empty
      <li class="px-4 py-10 text-center text-ink/45">{{ __('Aucune facture.') }}</li>
    @endforelse
  </ul>
  <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.tenant>
