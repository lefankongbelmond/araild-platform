@php
  $fmt = fn ($minor) => $currency->format((int) $minor);
  $raw = fn ($minor) => number_format(((int) $minor) / (10 ** $currency->minor_unit), $currency->minor_unit, ',', ' ');
  $locked = $budget?->isAdopted();
  $f = 'h-10 w-full rounded-xl border border-ink/10 bg-white px-3 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100';
  $bar = fn ($rate) => $rate === null ? 'bg-ink/10' : ($rate > 100 ? 'bg-gradient-to-r from-rose-500 to-red-600' : ($rate >= 80 ? 'bg-gradient-to-r from-amber-400 to-orange-500' : 'bg-gradient-to-r from-leaf-500 to-leaf-600'));
@endphp
<x-layouts.tenant title="Budget">
  <x-tenant.hero icon="pie" kicker="Facturation et finances · Gestion budgétaire" tone="violet">
    <x-slot:title>Budget {{ $year }}</x-slot:title>
    <x-slot:subtitle>Prévisions de recettes et de dépenses par compte du plan comptable, et exécution réelle calculée automatiquement à partir des écritures comptables validées.</x-slot:subtitle>
    <x-slot:actions>
      <form method="GET" class="flex items-center gap-2">
        <label for="bu-year" class="text-sm font-medium text-ink/60">Exercice</label>
        <select id="bu-year" name="annee" onchange="this.form.submit()" class="h-10 rounded-xl border border-ink/10 bg-white px-3 text-sm font-semibold">
          @foreach (collect(range(now()->year + 1, now()->year - 4))->merge($years)->unique()->sortDesc() as $y)<option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>@endforeach
        </select>
      </form>
      @if ($budget)<span @class(['inline-flex h-10 items-center rounded-xl px-3 text-sm font-bold', 'bg-leaf-50 text-leaf-700 ring-1 ring-leaf-100' => $locked, 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' => ! $locked])>{{ $locked ? 'Adopté le ' . $budget->adopted_on?->format('d/m/Y') : 'Brouillon' }}</span>@endif
    </x-slot:actions>
  </x-tenant.hero>

  @if (! $budget)
    <x-tenant.panel class="mt-5">
      <form method="POST" action="{{ route('budget.create') }}" class="grid gap-5 p-6 md:grid-cols-[1fr_auto] md:items-end sm:p-8">
        @csrf
        <input type="hidden" name="year" value="{{ $year }}">
        <div>
          <h2 class="text-lg font-bold text-[#0B1F3A]">Aucun budget pour {{ $year }}</h2>
          <p class="mt-1 text-sm text-ink/55">Créez le budget de l’exercice. Vous pourrez ajouter, modifier ou supprimer des lignes tant qu’il n’est pas adopté.</p>
          <div class="mt-4 grid gap-2 sm:grid-cols-3">
            @foreach (['' => ['Budget vide', 'Je saisis mes lignes une à une.'], 'accounts' => ['Une ligne par compte', 'Comptes de charges (6) et de produits (7), montants à 0.'], 'previous' => ['Reprendre le réalisé ' . ($year - 1), 'Montants = exécution réelle de l’exercice précédent.']] as $v => [$l, $d])
              <label class="flex cursor-pointer gap-3 rounded-2xl border border-ink/10 p-4 has-[:checked]:border-leaf-500 has-[:checked]:bg-leaf-50"><input type="radio" name="prefill" value="{{ $v }}" @checked($v === 'accounts') class="mt-1"><span><b class="block text-sm text-[#0B1F3A]">{{ $l }}</b><span class="text-xs text-ink/55">{{ $d }}</span></span></label>
            @endforeach
          </div>
        </div>
        <button class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-5 text-sm font-bold text-white shadow-lg shadow-leaf-500/25"><x-tenant.icon name="plus" :size="17"/> Créer le budget {{ $year }}</button>
      </form>
    </x-tenant.panel>
  @else
    @php
      $t = $totals;
      $rateI = $t['income_b'] > 0 ? round($t['income_r'] * 100 / $t['income_b'], 1) : null;
      $rateE = $t['expense_b'] > 0 ? round($t['expense_r'] * 100 / $t['expense_b'], 1) : null;
    @endphp
    <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <x-tenant.kpi label="Recettes prévues" :value="$fmt($t['income_b'])" icon="coins" tone="leaf" :hint="'Réalisé : ' . $fmt($t['income_r']) . ($rateI !== null ? ' (' . number_format($rateI, 1, ',', ' ') . ' %)' : '')" />
      <x-tenant.kpi label="Dépenses prévues" :value="$fmt($t['expense_b'])" icon="wallet" tone="amber" :hint="'Réalisé : ' . $fmt($t['expense_r']) . ($rateE !== null ? ' (' . number_format($rateE, 1, ',', ' ') . ' %)' : '')" />
      <x-tenant.kpi label="Solde prévu" :value="$fmt($t['income_b'] - $t['expense_b'])" icon="pie" tone="violet" />
      <x-tenant.kpi label="Solde réalisé" :value="$fmt($t['income_r'] - $t['expense_r'])" icon="chart" tone="brand" />
    </div>

    <form method="POST" action="{{ route('budget.lines.update', $budget) }}" class="mt-5 space-y-5">
      @csrf @method('PUT')
      @foreach (['income' => ['Recettes', 'coins'], 'expense' => ['Dépenses', 'wallet']] as $kind => [$label, $icon])
        <x-tenant.panel :title="$label" :icon="$icon">
          <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm">
              <thead><tr class="bg-[#EEF4FC] text-left text-[12.5px] font-semibold text-[#0B1F3A]/80">
                <th class="py-3 pl-6 pr-3">Compte</th><th class="px-3 py-3">Libellé</th><th class="px-3 py-3 text-right">Prévu ({{ $currency->code }})</th>
                <th class="px-3 py-3 text-right">Réalisé</th><th class="min-w-[180px] px-3 py-3">Exécution</th><th class="py-3 pl-3 pr-6"></th>
              </tr></thead>
              <tbody class="divide-y divide-ink/[.06]">
                @forelse ($rows[$kind] as $row)
                  @php $l = $row['line']; @endphp
                  <tr>
                    <td class="py-3 pl-6 pr-3">@if($l->account)<span class="inline-flex rounded-lg bg-brand-50 px-2 py-0.5 font-mono text-xs font-semibold text-brand-700 ring-1 ring-brand-100">{{ $l->account->number }}</span>@else<span class="text-xs text-ink/40">hors plan</span>@endif</td>
                    <td class="px-3 py-3 font-medium text-[#0B1F3A]">{{ $l->label }}</td>
                    <td class="px-3 py-3 text-right">
                      @if ($locked)<b class="tabular-nums">{{ $raw($l->amount_minor) }}</b>
                      @else<input name="amounts[{{ $l->id }}]" value="{{ $raw($l->amount_minor) }}" inputmode="decimal" class="{{ $f }} w-36 text-right tabular-nums" aria-label="Montant prévu {{ $l->label }}">@endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{{ $row['realized'] === null ? '—' : $fmt($row['realized']) }}</td>
                    <td class="px-3 py-3">
                      @if ($row['rate'] !== null)
                        <div class="flex items-center gap-2"><div class="h-2.5 flex-1 overflow-hidden rounded-full bg-ink/[.06]"><div class="h-full rounded-full {{ $bar($row['rate']) }}" style="width: {{ min(100, $row['rate']) }}%"></div></div><span class="w-14 text-right text-xs font-semibold tabular-nums">{{ number_format($row['rate'], 1, ',', ' ') }} %</span></div>
                      @else<span class="text-xs text-ink/40">{{ $l->account ? 'Montant prévu à 0' : 'Non rattaché à un compte' }}</span>@endif
                    </td>
                    <td class="py-3 pl-3 pr-6 text-right">
                      @unless ($locked)
                        <button form="del-{{ $l->id }}" class="grid h-9 w-9 place-items-center rounded-lg text-clay hover:bg-clay/10" title="Supprimer la ligne" aria-label="Supprimer {{ $l->label }}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                      @endunless
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-ink/50">Aucune ligne de {{ mb_strtolower($label) }}.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </x-tenant.panel>
      @endforeach
      <x-tenant.panel title="Notes et justification" icon="file">
        <div class="p-5 sm:p-6">
          @if ($locked)<p class="whitespace-pre-line text-sm text-ink/70">{{ $budget->notes ?: '—' }}</p>
          @else<textarea name="notes" rows="3" maxlength="2000" class="w-full rounded-xl border border-ink/10 px-3.5 py-2.5 text-sm" placeholder="Hypothèses retenues, décisions du conseil d’administration…">{{ $budget->notes }}</textarea>@endif
        </div>
      </x-tenant.panel>
      @unless ($locked)
        <div class="flex justify-end"><button class="inline-flex h-11 items-center gap-2 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-6 text-sm font-bold text-white shadow-lg shadow-leaf-500/25">Enregistrer les montants</button></div>
      @endunless
    </form>
    @unless ($locked)
      @foreach ($rows as $list) @foreach ($list as $row)
        <form id="del-{{ $row['line']->id }}" method="POST" action="{{ route('budget.lines.destroy', [$budget, $row['line']]) }}" onsubmit="return confirm('Supprimer cette ligne du budget ?')">@csrf @method('DELETE')</form>
      @endforeach @endforeach

      <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <x-tenant.panel title="Ajouter une ligne" icon="plus">
          <form method="POST" action="{{ route('budget.lines.store', $budget) }}" class="grid gap-3 p-5 sm:grid-cols-2 sm:p-6">
            @csrf
            <label class="block sm:col-span-2"><span class="mb-1.5 block text-[13px] font-semibold">Compte du plan comptable</span>
              <select name="account_id" class="{{ $f }} h-11"><option value="">— Ligne hors plan (libellé libre) —</option>
                @foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->number }} · {{ $a->label }}</option>@endforeach</select></label>
            <label class="block"><span class="mb-1.5 block text-[13px] font-semibold">Type (ligne hors plan)</span>
              <select name="kind" class="{{ $f }} h-11"><option value="expense">Dépense</option><option value="income">Recette</option></select></label>
            <label class="block"><span class="mb-1.5 block text-[13px] font-semibold">Montant prévu ({{ $currency->code }})</span>
              <input name="amount" required inputmode="decimal" class="{{ $f }} h-11 tabular-nums" placeholder="Ex. 2 500 000"></label>
            <label class="block sm:col-span-2"><span class="mb-1.5 block text-[13px] font-semibold">Libellé</span>
              <input name="label" maxlength="180" class="{{ $f }} h-11" placeholder="Laissez vide pour reprendre le libellé du compte"></label>
            @if ($errors->any())<div class="text-xs text-clay sm:col-span-2">{{ $errors->first() }}</div>@endif
            <button class="h-11 rounded-xl bg-[#0B1F3A] text-sm font-bold text-white hover:bg-brand-700 sm:col-span-2">Ajouter la ligne</button>
          </form>
        </x-tenant.panel>
        @if (auth()->user()?->hasRole('mutual_admin'))
          <x-tenant.panel title="Adopter le budget" icon="check">
            <form method="POST" action="{{ route('budget.adopt', $budget) }}" class="space-y-3 p-5 sm:p-6" onsubmit="return confirm('Adopter le budget ? Il ne sera plus modifiable.')">
              @csrf
              <p class="text-sm text-ink/60">Après le vote de l’assemblée générale (ou du conseil), adoptez le budget : il est verrouillé et son exécution reste suivie automatiquement.</p>
              <label class="block"><span class="mb-1.5 block text-[13px] font-semibold">Date d’adoption</span><input type="date" name="adopted_on" value="{{ now()->toDateString() }}" required class="{{ $f }} h-11"></label>
              <button class="h-11 w-full rounded-xl bg-gradient-to-r from-violet-500 to-violet-700 text-sm font-bold text-white">Adopter le budget {{ $year }}</button>
            </form>
          </x-tenant.panel>
        @endif
      </div>
    @endunless
    <p class="mt-4 text-xs text-ink/45">Réalisé : écritures validées ou comptabilisées de l’exercice (produits = crédits − débits ; charges = débits − crédits).</p>
  @endif
</x-layouts.tenant>
