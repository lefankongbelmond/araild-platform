@php
  $K = array_map('__', \App\Models\Tenant\AccountingEntry::KINDS);
  $S = array_map('__', \App\Models\Tenant\AccountingEntry::STATUSES);
  $f = 'rounded-md border border-ink/15 bg-white px-3 py-2 text-sm';
  $me = auth()->id();
  $byNumber = $accounts->keyBy('number');
  // Pré-remplissage possible par l'URL (ex. affectation du résultat depuis l'écran de clôture).
  $preAccount = fn ($k) => old($k) ?? $byNumber->get((string) request($k))?->id;
  $acc = fn ($a) => $a ? $a->number . ' — ' . $a->label : '—';
@endphp
<x-layouts.tenant title="{{ __('Opérations diverses') }}">
  @include('tenant.accounting._nav')
  <x-tenant.hero icon="book" kicker="Comptabilité" tone="violet">
    <x-slot:title>{{ __('Opérations diverses') }}</x-slot:title>
    <x-slot:subtitle>{{ __('Écritures saisies à la main : achats, salaires, amortissements, affectation du résultat… Le trésorier-comptable saisit ; l’administrateur ou le contrôleur valide. Personne ne valide sa propre saisie, et seules les écritures validées entrent dans les états.') }}</x-slot:subtitle>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  @if ($canCapture)
    <form method="POST" action="{{ route('accounting.entries.store') }}" class="mt-6 rounded-lg border border-ink/10 bg-white p-5">@csrf
      <h2 class="font-semibold">{{ __('Saisir une opération') }}</h2>
      <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <label class="min-w-0 text-xs text-ink/60">{{ __('Date') }}<input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" class="{{ $f }} mt-1 block w-full"></label>
        <label class="min-w-0 text-xs text-ink/60">{{ __('Pièce (facultatif)') }}<input name="piece" value="{{ old('piece') }}" maxlength="60" placeholder="{{ __('ex. FACT-2026-014') }}" class="{{ $f }} mt-1 block w-full"></label>
        <label class="min-w-0 text-xs text-ink/60">{{ __('Montant (:currency)', ['currency' => $currency->code]) }}<input name="amount" value="{{ old('amount', request('montant')) }}" inputmode="decimal" class="{{ $f }} mt-1 block w-full"></label>
        <label class="min-w-0 text-xs text-ink/60">{{ __('Compte débité') }}<select name="debit" class="{{ $f }} mt-1 block w-full">
          <option value="">{{ __('Choisir…') }}</option>
          @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((string) $preAccount('debit') === (string) $a->id)>{{ $acc($a) }}</option>@endforeach
        </select></label>
        <label class="min-w-0 text-xs text-ink/60">{{ __('Compte crédité') }}<select name="credit" class="{{ $f }} mt-1 block w-full">
          <option value="">{{ __('Choisir…') }}</option>
          @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((string) $preAccount('credit') === (string) $a->id)>{{ $acc($a) }}</option>@endforeach
        </select></label>
        <label class="min-w-0 text-xs text-ink/60">{{ __('Libellé') }}<input name="label" value="{{ old('label', request('libelle')) }}" maxlength="255" class="{{ $f }} mt-1 block w-full"></label>
      </div>
      <div class="mt-3 flex flex-wrap items-center gap-3">
        <button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white">{{ __('Enregistrer (à valider)') }}</button>
        <a href="{{ route('accounting.chart') }}" class="text-sm text-pine hover:underline">{{ __('Un compte manque ? Plan comptable') }}</a>
      </div>
    </form>
  @endif

  <section class="mt-6">
    <h2 class="font-semibold">{{ __('En attente de validation') }} <span class="text-sm font-normal text-ink/50">({{ $pending->count() }})</span></h2>
    <div class="mt-2 space-y-3">
      @forelse ($pending as $e)
        <article class="rounded-lg border border-amber-200 bg-white p-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 text-sm">
              <div class="flex flex-wrap items-center gap-2"><b>{{ $e->label }}</b><span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">{{ __(':kind · à valider', ['kind' => $K[$e->kind] ?? $e->kind]) }}</span></div>
              <div class="mt-1 text-ink/60">{{ __(':date · pièce', ['date' => $e->accountingDay?->day?->format('d/m/Y')]) }} <span class="font-mono text-xs">{{ $e->piece }}</span> {{ __('· saisie par :name', ['name' => $e->capturedBy?->name ?? '—']) }}</div>
              <div class="mt-1">{{ __('Débit') }} <span class="font-mono">{{ $e->debitAccount?->number }}</span> {{ __(':label → Crédit', ['label' => $e->debitAccount?->label]) }} <span class="font-mono">{{ $e->creditAccount?->number }}</span> {{ $e->creditAccount?->label }}</div>
            </div>
            <div class="text-right font-semibold tabular-nums whitespace-nowrap">{{ $currency->format($e->amount_minor) }}</div>
          </div>
          @if ($canDecide)
            @if ((int) $e->captured_by === (int) $me)
              <p class="mt-3 text-xs text-ink/55">{{ __('Vous avez saisi cette écriture : une autre personne doit la valider.') }}</p>
            @else
              <div class="mt-3 flex flex-wrap items-start gap-2">
                <form method="POST" action="{{ route('accounting.entries.validate', $e) }}">@csrf<button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white">{{ __('Valider') }}</button></form>
                <details class="min-w-0">
                  <summary class="cursor-pointer rounded-md border border-clay/30 px-4 py-2 text-sm text-clay">{{ __('Rejeter') }}</summary>
                  <form method="POST" action="{{ route('accounting.entries.reject', $e) }}" class="mt-2 flex flex-wrap items-end gap-2">@csrf
                    <label class="min-w-0 text-xs text-ink/60">{{ __('Motif') }}<input name="note" maxlength="500" required class="{{ $f }} mt-1 block" style="max-width:100%"></label>
                    <button class="rounded-md border border-clay/30 bg-white px-3 py-2 text-sm font-medium text-clay">{{ __('Confirmer le rejet') }}</button>
                  </form>
                </details>
              </div>
            @endif
          @endif
        </article>
      @empty
        <p class="rounded-lg border border-ink/10 bg-white px-4 py-6 text-center text-sm text-ink/45">{{ __('Aucune écriture en attente.') }}</p>
      @endforelse
    </div>
  </section>

  <section class="mt-8">
    <h2 class="font-semibold">{{ __('Dernières opérations décidées') }}</h2>
    <div class="relative mt-2 overflow-x-auto rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr>
          <th class="px-3 py-2 font-medium">{{ __('Date') }}</th><th class="px-3 py-2 font-medium">{{ __('Pièce') }}</th><th class="px-3 py-2 font-medium">{{ __('Opération') }}</th>
          <th class="px-3 py-2 text-right font-medium">{{ __('Montant') }}</th><th class="px-3 py-2 font-medium">{{ __('Décision') }}</th>
          @if ($canReverse)<th class="px-3 py-2 font-medium"><span class="sr-only">{{ __('Actions') }}</span></th>@endif
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($recent as $e)
            <tr>
              <td class="px-3 py-2 whitespace-nowrap">{{ $e->accountingDay?->day?->format('d/m/Y') }}</td>
              <td class="px-3 py-2 font-mono text-xs">{{ $e->piece }}</td>
              <td class="px-3 py-2">{{ $e->label }}<div class="text-xs text-ink/50"><span class="font-mono">{{ $e->debitAccount?->number }}</span> → <span class="font-mono">{{ $e->creditAccount?->number }}</span> · {{ $K[$e->kind] ?? $e->kind }}</div></td>
              <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $currency->format($e->amount_minor) }}</td>
              <td class="px-3 py-2 text-xs">
                <span class="rounded-full px-2 py-0.5 {{ $e->status === 'rejected' ? 'bg-clay/10 text-clay' : 'bg-mist text-pine' }}">{{ $S[$e->status] ?? $e->status }}</span>
                @if (in_array($e->id, $reversed)) <span class="rounded-full bg-mist px-2 py-0.5 text-ink/60">{{ __('Extournée') }}</span>@endif
                <div class="mt-1 text-ink/55">{{ __('par :name', ['name' => $e->validatedBy?->name ?? '—']) }}@if ($e->validated_at){{ __(', le :date', ['date' => $e->validated_at->format('d/m/Y')]) }}@endif</div>
                @if ($e->decision_note)<div class="mt-1 text-ink/70">{{ __('Motif : :decision_note', ['decision_note' => $e->decision_note]) }}</div>@endif
              </td>
              @if ($canReverse)
                <td class="px-3 py-2">
                  @if ($e->status !== 'rejected' && $e->kind === 'manual' && ! in_array($e->id, $reversed))
                    <details>
                      <summary class="cursor-pointer text-xs text-pine">{{ __('Extourner') }}</summary>
                      <form method="POST" action="{{ route('accounting.entries.reverse', $e) }}" class="mt-2 flex flex-wrap items-end gap-2">@csrf
                        <label class="text-xs text-ink/60">{{ __('Date') }}<input type="date" name="date" value="{{ now()->toDateString() }}" class="{{ $f }} mt-1 block"></label>
                        <button class="rounded-md border border-ink/15 bg-white px-3 py-2 text-xs font-medium">{{ __('Préparer l’extourne') }}</button>
                      </form>
                    </details>
                  @endif
                </td>
              @endif
            </tr>
          @empty
            <tr><td colspan="{{ $canReverse ? 6 : 5 }}" class="px-4 py-6 text-center text-ink/45">{{ __('Aucune opération diverse pour l’instant.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <p class="mt-2 text-xs text-ink/50">{{ __('Les écritures de trésorerie s’extournent depuis le journal.') }}</p>
  </section>
</x-layouts.tenant>
