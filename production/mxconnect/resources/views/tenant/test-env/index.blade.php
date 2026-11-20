@php
  $rows = array_filter($inventory['reset']);
  $formulas = array_sum($inventory['formulas']);
  $in = 'mt-1 block w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm';
  $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
@endphp
<x-layouts.tenant title="{{ __('Mutuelle de test') }}">
  <x-tenant.hero icon="alert" kicker="Paramètres" tone="amber">
    <x-slot:title>{{ __('MUTUELLE TEST : réinitialiser les données d’essai') }}</x-slot:title>
    <x-slot:subtitle>{{ __(':name est marquée « mutuelle de test » par l’administration du réseau. Ses données servent aux essais et peuvent être remises à zéro.', ['name' => $mutual->name]) }}</x-slot:subtitle>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid items-start gap-6 lg:grid-cols-2">
    <section class="rounded-lg border border-ink/10 bg-white p-5">
      <h2 class="font-semibold text-navy">{{ __('Ce qui sera effacé') }}</h2>
      <p class="mt-1 text-sm text-ink/60">{{ __('Toute l’activité d’essai : :count ligne(s)', ['count' => $n(array_sum($rows))]) }}{{ $inventory['links'] ? __(', et :count compte(s) adhérent relié(s) au portail', ['count' => $inventory['links']]) : '' }}.</p>
      @if ($rows)
        <ul class="mt-3 divide-y divide-ink/5 text-sm">
          @foreach ($rows as $t => $v)
            <li class="flex justify-between gap-3 py-1.5"><span>{{ __(\App\Services\TestDataResetService::LABELS[$t] ?? $t) }}</span><span class="tabular-nums">{{ $n($v) }}</span></li>
          @endforeach
        </ul>
      @else
        <p class="mt-3 text-sm text-ink/55">{{ __('Aucune donnée d’activité : la mutuelle de test est déjà vide.') }}</p>
      @endif
      <h2 class="mt-5 font-semibold text-navy">{{ __('Ce qui est conservé') }}</h2>
      <p class="mt-1 text-sm text-ink/60">{{ __('L’équipe et les rôles, le plan comptable, les actes, médicaments et catégories de soins, les antennes, les prestataires conventionnés, les réglages et les journaux d’audit. Les formules et garanties sont conservées, sauf si vous cochez l’option.') }}</p>
      @if ($inventory['unclassified'])
        <p class="mt-3 rounded-md border border-clay/30 bg-clay/5 px-3 py-2 text-sm text-clay">{{ __('Réinitialisation bloquée : table(s) non classée(s) :tables. Contactez l’administration du réseau.', ['tables' => implode(', ', $inventory['unclassified'])]) }}</p>
      @endif
    </section>

    <form method="POST" action="{{ route('test-env.reset') }}" class="rounded-lg border border-clay/30 bg-white p-5"
          onsubmit="return window.confirm('Effacer définitivement les données d’essai ? Une sauvegarde est faite avant.')">@csrf
      <h2 class="font-semibold text-clay">{{ __('Réinitialiser') }}</h2>
      <p class="mt-1 text-sm text-ink/60">{{ __('Une sauvegarde complète de la mutuelle est faite juste avant ; si elle échoue, rien n’est effacé. Aucune autre mutuelle n’est touchée. L’opération est inscrite dans le journal d’audit et dans l’historique de la mutuelle.') }}</p>
      <label class="mt-4 flex items-start gap-2 text-sm"><input type="checkbox" name="formulas" value="1" class="mt-1" @checked(old('formulas'))> <span>{{ __('Effacer aussi les formules et garanties (:value ligne(s))', ['value' => $n($formulas)]) }}</span></label>
      <label class="mt-2 flex items-start gap-2 text-sm"><input type="checkbox" name="examples" value="1" class="mt-1" @checked(old('examples', true))> <span>{{ __('… puis recharger les formules d’exemple') }}</span></label>
      <label class="mt-4 block text-sm font-medium">{{ __('Motif *') }}<input name="note" required minlength="5" maxlength="300" value="{{ old('note') }}" class="{{ $in }}" placeholder="{{ __('Ex. remise à zéro avant une formation') }}"></label>
      <label class="mt-3 block text-sm font-medium">{{ __('Pour confirmer, saisissez') }} <b>{{ $mutual->slug }}</b> *<input name="confirm" required autocomplete="off" class="{{ $in }}"></label>
      <button class="mt-4 w-full rounded-md bg-clay px-4 py-2.5 text-sm font-medium text-white">{{ __('Réinitialiser les données de test') }}</button>
    </form>
  </div>
</x-layouts.tenant>
