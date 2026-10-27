@php
  $c = $currency; $fmt = fn ($m) => (int) $m === 0 ? '—' : $c->format((int) $m);
  $money = fn ($m) => $c->format((int) $m);
  $f = 'rounded-md border border-ink/15 bg-white px-3 py-2 text-sm';
  $tabs = \App\Http\Controllers\Tenant\AccountingStatementController::STATEMENTS;
  $title = $tabs[$etat][1];
  $periodQuery = array_filter(['exercice' => $year, 'du' => request('du'), 'au' => request('au')]);
  $th = 'px-3 py-2 font-medium';
  $td = 'px-3 py-2';
  $num = 'px-3 py-2 text-right tabular-nums whitespace-nowrap';
@endphp
<x-layouts.tenant :title="$title">
  <div class="print:hidden flex flex-wrap items-center justify-between gap-2">
    <a href="{{ route('treasury.dashboard') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.treasury.title') }}</a>
    <span class="flex flex-wrap gap-3 text-sm">
      <a href="{{ route('accounting.journal') }}" class="text-pine hover:underline">{{ __('mxconnect.accounting.journal') }}</a>
      <a href="{{ route('accounting.chart') }}" class="text-pine hover:underline">{{ __('mxconnect.accounting.chart') }}</a>
    </span>
  </div>
  <x-tenant.hero icon="book" kicker="Comptabilité SYSCOHADA" tone="violet">
    <x-slot:title>{{ $title }}</x-slot:title>
    <x-slot:subtitle>{{ tenant('name') }} — {{ $etat === 'sheet' ? 'situation au ' . $to->format('d/m/Y') : 'du ' . $from->format('d/m/Y') . ' au ' . $to->format('d/m/Y') }} (exercice {{ $year }}). Calculé à l’instant depuis le journal : écritures validées seulement ; montants en {{ $c->code }}, jamais convertis.</x-slot:subtitle>
    <x-slot:actions>
      <button type="button" onclick="window.print()" class="print:hidden rounded-md border border-ink/10 bg-white px-4 py-2 text-sm font-medium text-ink/70 hover:bg-mist">Imprimer</button>
      <a href="{{ route('accounting.statements.export', ['etat' => $etat] + $periodQuery + ($etat === 'ledger' && ! empty($ledger) ? ['compte' => request('compte', $ledger['account']->number)] : [])) }}" class="print:hidden rounded-md bg-pine px-4 py-2 text-sm font-medium text-white">Exporter (CSV)</a>
    </x-slot:actions>
  </x-tenant.hero>

  <nav class="print:hidden mt-5 flex flex-wrap gap-2" aria-label="États comptables">
    @foreach ($tabs as $k => [$route, $label])
      <a href="{{ route($route, $periodQuery) }}" @if ($k === $etat) aria-current="page" @endif
         class="rounded-full border px-4 py-1.5 text-sm font-medium {{ $k === $etat ? 'border-pine bg-pine text-white' : 'border-ink/15 bg-white text-ink/70 hover:bg-mist' }}">{{ $label }}</a>
    @endforeach
  </nav>

  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <form method="GET" class="print:hidden mt-4 flex flex-wrap items-end gap-2">
    <label class="text-xs text-ink/60">Exercice<select name="exercice" class="{{ $f }} mt-1 block">
      @foreach ($years as $y)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endforeach
    </select></label>
    @if ($etat !== 'sheet')
      <label class="text-xs text-ink/60">Du (facultatif)<input type="date" name="du" value="{{ request('du') }}" class="{{ $f }} mt-1 block"></label>
    @endif
    <label class="text-xs text-ink/60">{{ $etat === 'sheet' ? 'Situation au (facultatif)' : 'Au (facultatif)' }}<input type="date" name="au" value="{{ request('au') }}" class="{{ $f }} mt-1 block"></label>
    @if ($etat === 'ledger' && count($accounts))
      <label class="text-xs text-ink/60" style="max-width:100%">Compte<select name="compte" class="{{ $f }} mt-1 block" style="max-width:100%">
        @foreach ($accounts as $a)<option value="{{ $a->number }}" @selected($ledger && $ledger['account']->id === $a->id)>{{ $a->number }} — {{ $a->label }}</option>@endforeach
      </select></label>
    @endif
    <button class="rounded-md bg-navy px-4 py-2 text-sm font-medium text-white">Afficher</button>
  </form>

  @if ($pending > 0)
    <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">{{ $pending }} écriture(s) de la période attendent encore leur validation : elles ne sont pas comptées ici.</div>
  @endif

  @if ($etat === 'balance')
    <div class="relative mt-6 overflow-x-auto rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60">
          <tr><th class="{{ $th }}" rowspan="2">Compte</th><th class="{{ $th }}" rowspan="2">Intitulé</th>
            <th class="{{ $th }} text-center" colspan="2">Soldes d’ouverture</th><th class="{{ $th }} text-center" colspan="2">Mouvements de la période</th><th class="{{ $th }} text-center" colspan="2">Soldes de clôture</th></tr>
          <tr>@for ($i = 0; $i < 3; $i++)<th class="{{ $th }} text-right">Débit</th><th class="{{ $th }} text-right">Crédit</th>@endfor</tr>
        </thead>
        <tbody class="divide-y divide-ink/5">
          @php $class = null; @endphp
          @forelse ($balance['rows'] as $r)
            @if ($r['class'] !== $class)
              @php $class = $r['class']; @endphp
              <tr class="bg-mist/50"><td colspan="8" class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-ink/50">Classe {{ $class }}</td></tr>
            @endif
            <tr>
              <td class="{{ $td }} font-mono">{{ $r['number'] }}</td>
              <td class="{{ $td }} {{ $r['computed'] ? 'text-ink/60' : '' }}" @if ($r['computed']) style="font-style:italic" @endif>{{ $r['label'] }}</td>
              <td class="{{ $num }}">{{ $fmt($r['open_d']) }}</td><td class="{{ $num }}">{{ $fmt($r['open_c']) }}</td>
              <td class="{{ $num }}">{{ $fmt($r['mov_d']) }}</td><td class="{{ $num }}">{{ $fmt($r['mov_c']) }}</td>
              <td class="{{ $num }} font-medium">{{ $fmt($r['close_d']) }}</td><td class="{{ $num }} font-medium">{{ $fmt($r['close_c']) }}</td>
            </tr>
          @empty
            <tr><td colspan="8" class="px-4 py-10 text-center text-ink/45">Aucune écriture validée sur cette période.</td></tr>
          @endforelse
        </tbody>
        @if (count($balance['rows']))
          <tfoot class="border-t border-ink/15 font-semibold"><tr>
            <td class="{{ $td }}" colspan="2">Totaux</td>
            @foreach (['open_d', 'open_c', 'mov_d', 'mov_c', 'close_d', 'close_c'] as $k)<td class="{{ $num }}">{{ $money($balance['totals'][$k]) }}</td>@endforeach
          </tr></tfoot>
        @endif
      </table>
    </div>
    <p class="mt-3 text-sm {{ $balance['balanced'] ? 'text-pine' : 'text-clay' }}">{{ $balance['balanced'] ? '✓ Balance équilibrée : total des débits = total des crédits, dans chaque colonne.' : '⚠ Balance déséquilibrée : signalez-le à l’administrateur de la plateforme.' }}</p>

  @elseif ($etat === 'ledger')
    @if (! $ledger)
      <div class="mt-6 rounded-lg border border-ink/10 bg-white px-4 py-10 text-center text-sm text-ink/45">Aucune écriture validée dans le journal pour l’instant.</div>
    @else
      <h2 class="mt-6 font-semibold">{{ $ledger['account']->number }} — {{ $ledger['account']->label }} <span class="text-sm font-normal text-ink/50">(classe {{ $ledger['account']->class }})</span></h2>
      <div class="relative mt-2 overflow-x-auto rounded-lg border border-ink/10 bg-white">
        <table class="w-full text-sm">
          <thead class="bg-mist text-left text-ink/60"><tr>
            <th class="{{ $th }}">Date</th><th class="{{ $th }}">Pièce</th><th class="{{ $th }}">Libellé</th><th class="{{ $th }}">Contrepartie</th>
            <th class="{{ $th }} text-right">Débit</th><th class="{{ $th }} text-right">Crédit</th><th class="{{ $th }} text-right">Solde</th>
          </tr></thead>
          <tbody class="divide-y divide-ink/5">
            <tr class="text-ink/60"><td class="{{ $td }}" colspan="6">Solde d’ouverture au {{ $from->format('d/m/Y') }}</td><td class="{{ $num }}">{{ $money($ledger['opening']) }}</td></tr>
            @forelse ($ledger['rows'] as $r)
              <tr>
                <td class="{{ $td }} whitespace-nowrap">{{ $r['date']?->format('d/m/Y') }}</td>
                <td class="{{ $td }} font-mono text-xs">{{ $r['piece'] }}</td>
                <td class="{{ $td }}">{{ $r['label'] }}</td>
                <td class="{{ $td }} font-mono" title="{{ $r['contra_label'] }}">{{ $r['contra'] }}</td>
                <td class="{{ $num }}">{{ $fmt($r['debit']) }}</td><td class="{{ $num }}">{{ $fmt($r['credit']) }}</td><td class="{{ $num }}">{{ $money($r['balance']) }}</td>
              </tr>
            @empty
              <tr><td colspan="7" class="px-4 py-6 text-center text-ink/45">Aucun mouvement sur cette période.</td></tr>
            @endforelse
          </tbody>
          <tfoot class="border-t border-ink/15 font-semibold"><tr>
            <td class="{{ $td }}" colspan="4">Totaux et solde de clôture au {{ $to->format('d/m/Y') }}</td>
            <td class="{{ $num }}">{{ $money($ledger['debit']) }}</td><td class="{{ $num }}">{{ $money($ledger['credit']) }}</td><td class="{{ $num }}">{{ $money($ledger['closing']) }}</td>
          </tr></tfoot>
        </table>
      </div>
      <p class="mt-3 text-xs text-ink/50">Solde positif : débiteur ; négatif : créditeur. Comptes de gestion (classes 6 à 8) : le solde repart de zéro au 1er janvier.</p>
    @endif

  @elseif ($etat === 'income')
    @php
      $block = function ($title, $rows, $totalLabel, $total) use ($money) {
          $h = '<section class="min-w-0 rounded-lg border border-ink/10 bg-white p-5"><h2 class="font-semibold">' . e($title) . '</h2><table class="mt-2 w-full text-sm"><tbody class="divide-y divide-ink/5">';
          foreach ($rows as $r) {
              $h .= '<tr><td class="py-2 pr-3 font-mono">' . e($r['number']) . '</td><td class="py-2">' . e($r['label']) . '</td><td class="py-2 text-right tabular-nums whitespace-nowrap">' . e($money($r['amount'])) . '</td></tr>';
          }
          if (! count($rows)) {
              $h .= '<tr><td colspan="3" class="py-4 text-center text-ink/45">Aucun montant sur la période.</td></tr>';
          }

          return $h . '</tbody><tfoot class="border-t border-ink/15 font-semibold"><tr><td class="py-2" colspan="2">' . e($totalLabel) . '</td><td class="py-2 text-right tabular-nums whitespace-nowrap">' . e($money($total)) . '</td></tr></tfoot></table></section>';
      };
      $res = $income['resultat'];
    @endphp
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
      {!! $block('Charges (classe 6)', $income['charges'], 'Total des charges', $income['total_charges']) !!}
      {!! $block('Produits (classe 7)', $income['produits'], 'Total des produits', $income['total_produits']) !!}
    </div>
    @if (count($income['hao_charges']) || count($income['hao_produits']))
      <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {!! $block('Charges hors activités ordinaires (classe 8)', $income['hao_charges'], 'Total', array_sum(array_column($income['hao_charges'], 'amount'))) !!}
        {!! $block('Produits hors activités ordinaires (classe 8)', $income['hao_produits'], 'Total', array_sum(array_column($income['hao_produits'], 'amount'))) !!}
      </div>
    @endif
    <section class="mt-6 rounded-lg border border-ink/10 bg-white p-5">
      <dl class="divide-y divide-ink/5 text-sm">
        <div class="flex flex-wrap justify-between gap-2 py-2"><dt class="text-ink/60">Résultat des activités ordinaires (produits − charges)</dt><dd class="font-semibold tabular-nums">{{ $money($income['resultat_ordinaire']) }}</dd></div>
        @if ($income['resultat_hao'] !== 0)<div class="flex flex-wrap justify-between gap-2 py-2"><dt class="text-ink/60">Résultat hors activités ordinaires</dt><dd class="font-semibold tabular-nums">{{ $money($income['resultat_hao']) }}</dd></div>@endif
        <div class="flex flex-wrap justify-between gap-2 py-3" style="font-size:1rem"><dt class="font-semibold">Résultat net : {{ $res > 0 ? 'excédent' : ($res < 0 ? 'déficit' : 'équilibre') }}</dt><dd class="font-semibold tabular-nums {{ $res < 0 ? 'text-clay' : 'text-pine' }}">{{ $money($res) }}</dd></div>
      </dl>
    </section>

  @else
    @php
      $groups = \App\Http\Controllers\Tenant\AccountingStatementController::SHEET_GROUPS;
      $side = function ($key, $title) use ($sheet, $groups, $money) {
          $h = '<section class="min-w-0 rounded-lg border border-ink/10 bg-white p-5"><h2 class="font-semibold">' . e($title) . '</h2><table class="mt-2 w-full text-sm"><tbody>';
          foreach ($groups[$key] as $g => $label) {
              $rows = $sheet[$key][$g];
              if (! count($rows)) { continue; }
              $h .= '<tr><td colspan="3" class="pt-3 pb-1 text-xs font-semibold uppercase tracking-wide text-ink/50">' . e($label) . '</td></tr>';
              foreach ($rows as $r) {
                  $h .= '<tr class="border-t border-ink/5"><td class="py-2 pr-3 font-mono">' . e($r['number']) . '</td><td class="py-2' . (! empty($r['computed']) ? ' text-ink/60" style="font-style:italic' : '') . '">' . e($r['label']) . '</td><td class="py-2 text-right tabular-nums whitespace-nowrap">' . e($money($r['amount'])) . '</td></tr>';
              }
          }

          if (! array_sum(array_map('count', $sheet[$key]))) {
              $h .= '<tr><td colspan="3" class="py-4 text-center text-ink/45">Aucun solde à cette date.</td></tr>';
          }

          return $h . '</tbody><tfoot class="border-t border-ink/15 font-semibold"><tr><td class="py-2" colspan="2">Total ' . e(mb_strtolower($title)) . '</td><td class="py-2 text-right tabular-nums whitespace-nowrap">' . e($money($sheet['total_' . $key])) . '</td></tr></tfoot></table></section>';
      };
    @endphp
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
      {!! $side('actif', 'Actif') !!}
      {!! $side('passif', 'Passif') !!}
    </div>
    <p class="mt-3 text-sm {{ $sheet['balanced'] ? 'text-pine' : 'text-clay' }}">{{ $sheet['balanced'] ? '✓ Bilan équilibré : total de l’actif = total du passif.' : '⚠ Bilan déséquilibré : signalez-le à l’administrateur de la plateforme.' }}</p>
    <p class="mt-1 text-xs text-ink/50">Bilan simplifié. Le résultat de l’exercice et le report à nouveau sont calculés depuis les comptes de gestion (aucune écriture de clôture n’est passée par MX-CONNECT). Un compte de tiers ou de trésorerie figure à l’actif ou au passif selon le sens de son solde.</p>
  @endif

  <p class="mt-4 text-xs text-ink/45">Édité le {{ now()->format('d/m/Y à H:i') }} par {{ auth()->user()?->name }} · MX-CONNECT · plan comptable SYSCOHADA de la mutuelle. Aucune conversion de devise.</p>
</x-layouts.tenant>
