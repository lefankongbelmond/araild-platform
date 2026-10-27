@php $f = 'rounded-md border border-ink/15 bg-white px-3 py-2 text-sm'; @endphp
<x-layouts.tenant title="Clôture de l’exercice">
  @include('tenant.accounting._nav')
  <x-tenant.hero icon="lock" kicker="Comptabilité" tone="violet">
    <x-slot:title>Clôture de l’exercice</x-slot:title>
    <x-slot:subtitle>Une fois l’année terminée et toutes ses écritures validées, la clôture solde les comptes de charges et de produits dans le résultat (131 bénéfice ou 139 perte) et verrouille l’année : plus aucune écriture ni mouvement de trésorerie ne peut y être daté.</x-slot:subtitle>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 space-y-4">
    @foreach ($rows as $r)
      @php $y = $r['year']; $fy = $r['fy']; $closed = $fy?->isClosed(); @endphp
      <section class="rounded-lg border {{ $closed ? 'border-ink/10' : 'border-ink/15' }} bg-white p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="flex flex-wrap items-center gap-2 font-semibold">Exercice {{ $y }}
              <span class="rounded-full px-2 py-0.5 text-xs {{ $closed ? 'bg-mist text-pine' : 'bg-amber-50 text-amber-700' }}">{{ $closed ? 'Clôturé' : ($y >= now()->year ? 'En cours' : 'Ouvert') }}</span></h2>
            <p class="mt-1 text-sm text-ink/60">{{ $r['entries'] }} écriture(s) validée(s).
              @if ($closed) Clôturé le {{ $fy->closed_at?->format('d/m/Y') }} par {{ $users[$fy->closed_by] ?? '—' }}. @endif</p>
          </div>
          @if ($closed)
            <div class="text-right">
              <div class="text-xs uppercase tracking-wide text-ink/50">{{ $fy->result_minor >= 0 ? 'Bénéfice' : 'Perte' }}</div>
              <div class="text-lg font-semibold tabular-nums {{ $fy->result_minor < 0 ? 'text-clay' : 'text-pine' }}">{{ $currency->format(abs((int) $fy->result_minor)) }}</div>
            </div>
          @endif
        </div>

        @if ($closed)
          <div class="mt-3 flex flex-wrap items-start gap-3 text-sm">
            <a href="{{ route('accounting.balance', ['exercice' => $y]) }}" class="text-pine hover:underline">États {{ $y }}</a>
            @if ($canCapture && (int) $fy->result_minor !== 0)
              @php $profit = $fy->result_minor > 0; @endphp
              <a href="{{ route('accounting.entries.index', ['debit' => $profit ? '131' : '129', 'credit' => $profit ? '121' : '139', 'montant' => number_format(abs($fy->result_minor) / (10 ** $currency->minor_unit), $currency->minor_unit, ',', ''), 'libelle' => 'Affectation du résultat ' . $y . ' (décision de l’AG)']) }}" class="text-pine hover:underline">Préparer l’affectation du résultat</a>
            @endif
            @if ($canReopen)
              <details class="min-w-0">
                <summary class="cursor-pointer text-clay">Rouvrir l’exercice</summary>
                <form method="POST" action="{{ route('accounting.closing.reopen', $y) }}" class="mt-2 flex flex-wrap items-end gap-2">@csrf
                  <label class="min-w-0 text-xs text-ink/60">Motif (obligatoire)<input name="note" maxlength="500" required class="{{ $f }} mt-1 block" style="max-width:100%"></label>
                  <button class="rounded-md border border-clay/30 bg-white px-3 py-2 text-sm font-medium text-clay">Rouvrir</button>
                </form>
              </details>
            @endif
          </div>
          @if (count($fy->history ?? []) > 1)
            <ul class="mt-3 space-y-1 text-xs text-ink/55">
              @foreach ($fy->history as $h)
                <li>{{ \Illuminate\Support\Carbon::parse($h['at'])->format('d/m/Y H:i') }} — {{ $h['action'] === 'close' ? 'clôture' : 'réouverture' }} par {{ $users[$h['by']] ?? '—' }}@if (! empty($h['note'])) : {{ $h['note'] }}@endif</li>
              @endforeach
            </ul>
          @endif
          <p class="mt-3 text-xs text-ink/50">Affectation : selon la décision de l’assemblée générale, le résultat passe du compte 131 (ou 139) au report à nouveau (121 / 129) ou aux réserves (111, 118), par une opération diverse validée par une autre personne. Une fois le résultat affecté, l’exercice ne peut plus être rouvert sans extourner d’abord l’affectation.</p>
        @elseif (count($r['blockers']))
          <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-ink/60">
            @foreach ($r['blockers'] as $b)<li>{{ $b }}</li>@endforeach
          </ul>
        @elseif ($canClose)
          <form method="POST" action="{{ route('accounting.closing.close', $y) }}" class="mt-3" onsubmit="return confirm('Clôturer l’exercice {{ $y }} ? Plus aucune écriture ne pourra y être datée.')">@csrf
            <p class="text-sm text-ink/60">Tout est prêt : écritures validées, trésorerie comptabilisée.</p>
            <button class="mt-2 rounded-md bg-navy px-4 py-2 text-sm font-medium text-white">Clôturer l’exercice {{ $y }}</button>
          </form>
        @else
          <p class="mt-3 text-sm text-ink/60">Prêt à être clôturé (par le trésorier-comptable ou l’administrateur).</p>
        @endif
      </section>
    @endforeach
  </div>
</x-layouts.tenant>
