@php
  $c = $currency;
  $f = 'rounded-md border border-ink/15 bg-white px-3 py-2 text-sm';
  $modes = \App\Models\Tenant\ContributionPayment::MODES;
  $today = now()->startOfDay();
  $label = ['paid' => 'Payée', 'overdue' => 'En retard', 'to_pay' => 'À venir'];
@endphp
<x-layouts.tenant :title="'Cotisations — ' . $member->last_name . ' ' . $member->first_name">
  <div class="print:hidden flex flex-wrap items-center gap-x-4 text-sm" style="row-gap:.25rem">
    <a href="{{ route('contributions.index') }}" class="text-ink/50 hover:underline">&larr; {{ __('mxconnect.contribution.plural') }}</a>
    @acces('members.show')<a href="{{ route('members.show', $member) }}" class="text-pine hover:underline">Fiche de l’adhérent</a>@endacces
  </div>
  <x-tenant.hero icon="coins" kicker="Relevé de cotisations" tone="leaf">
    <x-slot:title>{{ $member->last_name }} {{ $member->first_name }}</x-slot:title>
    <x-slot:subtitle>{{ $member->member_code }}@if ($member->phone) · {{ $member->phone }}@endif · {{ tenant('name') }} · montants en {{ $c->code }}. Relevé arrêté au {{ $today->format('d/m/Y') }}.</x-slot:subtitle>
    <x-slot:actions>
      <button type="button" onclick="window.print()" class="print:hidden rounded-md border border-ink/10 bg-white px-4 py-2 text-sm font-medium text-ink/70 hover:bg-mist">Imprimer le relevé</button>
    </x-slot:actions>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-tenant.kpi label="Appelé à ce jour" :value="$c->format($summary['due_to_date'])" icon="file" tone="brand" hint="échéances déjà arrivées" />
    <x-tenant.kpi label="Total encaissé" :value="$c->format($summary['paid'])" icon="coins" tone="leaf" hint="tous encaissements" />
    <x-tenant.kpi label="En retard" :value="$c->format($summary['arrears'])" icon="alert" tone="amber" :hint="$summary['arrears'] ? 'droits suspendus jusqu’au paiement' : 'à jour'" />
    <x-tenant.kpi label="À venir" :value="$c->format($summary['upcoming'])" icon="history" tone="violet" hint="échéances futures" />
  </div>

  @if ($canCollect)
    <form method="POST" action="{{ route('contributions.collect', $member) }}" class="print:hidden mt-6 rounded-lg border border-ink/10 bg-white p-5">@csrf
      <h2 class="font-semibold">Encaisser</h2>
      <p class="mt-1 text-sm text-ink/60">Le montant est réparti sur les échéances impayées, la plus ancienne d’abord ; un acompte est possible. Reste à payer sur l’échéancier : <b>{{ $c->format($open) }}</b>.</p>
      <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label class="min-w-0 text-xs text-ink/60">Montant ({{ $c->code }})<input name="amount" value="{{ old('amount', $summary['arrears'] ? number_format($summary['arrears'] / (10 ** $c->minor_unit), $c->minor_unit, ',', '') : '') }}" inputmode="decimal" class="{{ $f }} mt-1 block w-full"></label>
        <label class="min-w-0 text-xs text-ink/60">Date<input type="date" name="paid_on" value="{{ old('paid_on', $today->toDateString()) }}" max="{{ $today->toDateString() }}" class="{{ $f }} mt-1 block w-full"></label>
        <label class="min-w-0 text-xs text-ink/60">Mode<select name="mode" class="{{ $f }} mt-1 block w-full">
          @foreach ($modes as $k => $l)<option value="{{ $k }}" @selected(old('mode', 'cash') === $k)>{{ $l }}</option>@endforeach
        </select></label>
        <label class="min-w-0 text-xs text-ink/60">Référence (banque, Mobile Money)<input name="reference" value="{{ old('reference') }}" maxlength="60" class="{{ $f }} mt-1 block w-full"></label>
      </div>
      <button class="mt-3 rounded-md bg-pine px-4 py-2 text-sm font-medium text-white" @disabled($open === 0)>Enregistrer l’encaissement</button>
      @if ($open === 0)<span class="ml-2 text-sm text-ink/55">Aucune échéance à régler.</span>@endif
    </form>
  @endif
  @if ($feeToCall > 0)
    <form method="POST" action="{{ route('contributions.fee', $member) }}" class="print:hidden mt-3 flex flex-wrap items-center gap-3 rounded-lg border border-ink/10 bg-white p-4 text-sm">@csrf
      <span class="min-w-0 flex-1 text-ink/70">La garantie prévoit un droit d’adhésion de <b>{{ $c->format($feeToCall) }}</b>, pas encore appelé pour cet adhérent.</span>
      <button class="rounded-md border border-ink/15 bg-white px-4 py-2 text-sm font-medium">Appeler le droit d’adhésion</button>
    </form>
  @endif

  <section class="mt-6">
    <h2 class="font-semibold">Échéancier</h2>
    <div class="relative mt-2 overflow-x-auto rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr>
          <th class="px-3 py-2 font-medium">Période</th><th class="px-3 py-2 font-medium">Échéance</th><th class="px-3 py-2 font-medium">Garantie</th>
          <th class="px-3 py-2 text-right font-medium">Montant</th><th class="px-3 py-2 text-right font-medium">Payé</th><th class="px-3 py-2 text-right font-medium">Reste</th><th class="px-3 py-2 font-medium">État</th>
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($schedules as $s)
            @php $paid = (int) $s->payments_sum_amount_minor; @endphp
            <tr>
              <td class="px-3 py-2 whitespace-nowrap">{{ $s->label() }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ $s->due_date?->format('d/m/Y') }}</td>
              <td class="px-3 py-2">{{ $s->subscription?->guaranteeVersion?->guarantee?->name }}</td>
              <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $c->format($s->due_minor) }}</td>
              <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $paid ? $c->format($paid) : '—' }}</td>
              <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $s->status === 'paid' ? '—' : $c->format(max(0, $s->due_minor - $paid)) }}</td>
              <td class="px-3 py-2 text-xs whitespace-nowrap"><span class="rounded-full px-2 py-0.5 {{ $s->status === 'paid' ? 'bg-mist text-pine' : ($s->status === 'overdue' ? 'bg-clay/10 text-clay' : 'bg-amber-50 text-amber-700') }}">{{ $label[$s->status] ?? $s->status }}{{ $s->status !== 'paid' && $paid ? ' (acompte)' : '' }}</span></td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-4 py-6 text-center text-ink/45">Aucune échéance : la souscription n’est pas encore validée.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="mt-6">
    <h2 class="font-semibold">Encaissements</h2>
    @php $byRef = $cancellations->groupBy('collection_ref'); $me = (int) auth()->id(); @endphp
    <div class="mt-2 space-y-3">
      @forelse ($collections as $ref => $rows)
        @php
          $first = $rows->first();
          $cancelled = $first->trashed();
          $reqs = $byRef[$first->collection_ref] ?? collect();
          $pending = $reqs->firstWhere('status', 'pending');
          $approved = $reqs->firstWhere('status', 'approved');
          $refused = $reqs->where('status', 'rejected')->sortByDesc('id')->first();
        @endphp
        <article class="rounded-lg border bg-white p-4 {{ $cancelled ? 'border-clay/30' : ($pending ? 'border-amber-200' : 'border-ink/10') }}">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 text-sm">
              <div class="flex flex-wrap items-center gap-2">
                @if ($first->receipt_path)<a href="{{ route('contributions.receipt', $first) }}" target="_blank" rel="noopener" class="font-mono text-xs text-pine hover:underline">{{ $first->collection_ref ?: 'Reçu' }}</a>
                @else<span class="font-mono text-xs">{{ $first->collection_ref ?: '—' }}</span>@endif
                <span class="text-ink/60">{{ $first->paid_on?->format('d/m/Y') }} · {{ $modes[$first->mode] ?? $first->mode }}@if ($first->reference) · {{ $first->reference }}@endif</span>
                @if ($cancelled)<span class="rounded-full bg-clay/10 px-2 py-0.5 text-xs text-clay">Annulé</span>
                @elseif ($pending)<span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">Annulation demandée</span>@endif
              </div>
              <div class="mt-1">{{ $rows->map(fn ($p) => $p->schedule?->label())->filter()->unique()->implode(', ') }}</div>
              @if ($first->recordedBy)<div class="text-xs text-ink/50">encaissé par {{ $first->recordedBy->name }}</div>@endif
              @if ($approved)<div class="mt-1 text-xs text-clay">Annulé le {{ $approved->decided_at?->format('d/m/Y') }} (demande de {{ $approved->requestedBy?->name }}, validée par {{ $approved->decidedBy?->name }}) — motif : {{ $approved->reason }}</div>@endif
              @if ($pending)<div class="mt-1 text-xs text-amber-700">Demandée par {{ $pending->requestedBy?->name }} — motif : {{ $pending->reason }}</div>@endif
              @if ($refused && ! $cancelled && ! $pending)<div class="mt-1 text-xs text-ink/55">Annulation refusée le {{ $refused->decided_at?->format('d/m/Y') }} : {{ $refused->decision_note }}</div>@endif
            </div>
            <div class="text-right font-semibold tabular-nums whitespace-nowrap" @if ($cancelled) style="text-decoration:line-through" @endif>{{ $c->format($rows->sum('amount_minor')) }}</div>
          </div>
          @if (! $cancelled && $first->collection_ref)
            @if ($pending && $canDecide)
              @if ((int) $pending->requested_by === $me)
                <p class="print:hidden mt-3 text-xs text-ink/55">Vous avez demandé cette annulation : une autre personne doit décider.</p>
              @else
                <div class="print:hidden mt-3 flex flex-wrap items-start gap-2">
                  <form method="POST" action="{{ route('contributions.cancel.approve', $pending) }}" onsubmit="return confirm('Annuler l’encaissement {{ $first->collection_ref }} ?')">@csrf<button class="rounded-md border border-clay/30 bg-white px-4 py-2 text-sm font-medium text-clay">Valider l’annulation</button></form>
                  <details class="min-w-0">
                    <summary class="cursor-pointer rounded-md border border-ink/15 px-4 py-2 text-sm">Refuser</summary>
                    <form method="POST" action="{{ route('contributions.cancel.reject', $pending) }}" class="mt-2 flex flex-wrap items-end gap-2">@csrf
                      <label class="min-w-0 text-xs text-ink/60">Motif du refus<input name="note" maxlength="500" required class="{{ $f }} mt-1 block" style="max-width:100%"></label>
                      <button class="rounded-md border border-ink/15 bg-white px-3 py-2 text-sm font-medium">Confirmer le refus</button>
                    </form>
                  </details>
                </div>
              @endif
            @elseif (! $pending && $canCancel)
              <details class="print:hidden mt-3">
                <summary class="cursor-pointer text-sm text-clay">Demander l’annulation (erreur de saisie)</summary>
                <form method="POST" action="{{ route('contributions.cancel.request', $first->collection_ref) }}" class="mt-2 flex flex-wrap items-end gap-2">@csrf
                  <label class="min-w-0 text-xs text-ink/60">Motif<input name="reason" maxlength="500" required class="{{ $f }} mt-1 block" style="max-width:100%"></label>
                  <button class="rounded-md border border-clay/30 bg-white px-3 py-2 text-sm font-medium text-clay">Envoyer la demande</button>
                </form>
                <p class="mt-1 text-xs text-ink/50">L’administrateur ou le contrôleur décide. Une fois validée, les échéances sont rouvertes et la trésorerie corrigée.</p>
              </details>
            @endif
          @endif
        </article>
      @empty
        <p class="rounded-lg border border-ink/10 bg-white px-4 py-6 text-center text-sm text-ink/45">Aucun encaissement.</p>
      @endforelse
    </div>
  </section>
  <p class="mt-4 text-xs text-ink/45">Édité le {{ now()->format('d/m/Y à H:i') }} par {{ auth()->user()?->name }} · MX-CONNECT. MX-CONNECT ne détient aucun fonds : il enregistre les sommes reçues par la mutuelle.</p>
</x-layouts.tenant>
