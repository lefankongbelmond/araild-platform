<x-layouts.tenant title="{{ __('Nouveau changement de formule') }}">
  @php
    $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm';
    $fmt = fn ($v) => $v === null ? '—' : $currency->format((int) $v);
  @endphp
  <x-tenant.hero icon="users" kicker="{{ __('Changements de formule') }}" tone="brand">
    <x-slot:title>{{ __('Nouveau changement de formule') }}</x-slot:title>
    <x-slot:subtitle>{{ __('1. Choisissez la formule actuelle, la formule cible et la date d’effet, puis « Voir les adhérents ». 2. Vérifiez la liste, décochez au besoin, indiquez le motif et envoyez : une autre personne validera.') }}</x-slot:subtitle>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-lg border border-clay/30 bg-white p-4 text-sm text-clay" role="alert"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="GET" action="{{ route('formula-migrations.create') }}" class="mt-6 grid gap-3 rounded-lg border border-ink/10 bg-white p-5 sm:grid-cols-2 lg:grid-cols-4">
    <label class="block text-sm font-medium">{{ __('Formule actuelle *') }}<select name="source" required class="{{ $f }}"><option value="">—</option>@foreach ($formulas as $g)<option value="{{ $g->id }}" @selected((int) $data['source'] === $g->id)>{{ $g->name }}</option>@endforeach</select></label>
    <label class="block text-sm font-medium">{{ __('Formule cible *') }}<select name="target" required class="{{ $f }}"><option value="">—</option>@foreach ($formulas as $g)<option value="{{ $g->id }}" @selected((int) $data['target'] === $g->id)>{{ $g->name }}</option>@endforeach</select></label>
    <label class="block text-sm font-medium">{{ __('Date d’effet *') }}<input type="date" name="date" required min="{{ now()->toDateString() }}" value="{{ $data['date'] }}" class="{{ $f }}"></label>
    <div class="flex items-end"><button class="w-full rounded-md bg-navy px-4 py-2 text-sm font-medium text-white">{{ __('Voir les adhérents') }}</button></div>
  </form>

  @if ($preview)
    @php $problem = \App\Services\Tenant\FormulaMigrationService::targetProblem($preview['version']); @endphp
    @if ($problem)
      <p class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $problem }}</p>
    @endif
    <form method="POST" action="{{ route('formula-migrations.store') }}" class="mt-6 space-y-4">@csrf
      <input type="hidden" name="source_guarantee_id" value="{{ $data['source'] }}"><input type="hidden" name="target_guarantee_id" value="{{ $data['target'] }}"><input type="hidden" name="effective_date" value="{{ $data['date'] }}">
      <section class="rounded-lg border border-ink/10 bg-white p-5">
        <h2 class="font-semibold text-navy">{{ __('Adhérents qui peuvent changer (:count)', ['count' => count($preview['eligible'])]) }}</h2>
        <p class="mt-1 text-xs text-ink/55">{{ __('Nouvelle cotisation = cotisation de base de la formule cible × nombre de bénéficiaires. Les échéances de l’ancienne formule dues à partir de la date d’effet et non réglées seront retirées.') }}</p>
        @if ($preview['eligible'])
          <div class="relative mt-3 overflow-x-auto">
            <table class="w-full text-sm" style="min-width:560px">
              <thead class="text-left text-ink/50"><tr><th class="py-1 pr-3"><span class="sr-only">{{ __('Choisir') }}</span></th><th class="py-1 pr-3 font-medium">{{ __('Adhérent') }}</th><th class="py-1 pr-3 text-right font-medium">{{ __('Cotisation actuelle') }}</th><th class="py-1 pr-3 text-right font-medium">{{ __('Nouvelle cotisation') }}</th><th class="py-1 text-right font-medium">{{ __('Échéances retirées') }}</th></tr></thead>
              <tbody class="divide-y divide-ink/5">
                @foreach ($preview['eligible'] as $r)
                  <tr><td class="py-2 pr-3"><input type="checkbox" name="member_ids[]" value="{{ $r['member_id'] }}" checked aria-label="Choisir {{ $r['name'] }}"></td>
                    <td class="py-2 pr-3">{{ $r['name'] }}<div class="text-xs text-ink/50">{{ $r['code'] }}</div></td>
                    <td class="py-2 pr-3 text-right tabular-nums">{{ $fmt($r['old_minor']) }}</td><td class="py-2 pr-3 text-right tabular-nums">{{ $fmt($r['new_minor']) }}</td>
                    <td class="py-2 text-right tabular-nums">{{ $r['removed'] }}</td></tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <p class="mt-3 text-sm text-ink/55">{{ __('Aucun adhérent ne peut changer de formule à cette date.') }}</p>
        @endif
      </section>
      @if ($preview['skipped'])
        <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
          <h2 class="font-semibold">{{ __('Adhérents écartés (:count)', ['count' => count($preview['skipped'])]) }}</h2>
          <ul class="mt-2 space-y-1">@foreach ($preview['skipped'] as $r)<li><b>{{ $r['name'] }}</b> ({{ $r['code'] }}) : {{ $r['reason'] }}</li>@endforeach</ul>
        </section>
      @endif
      @if ($preview['eligible'] && ! $problem)
        <section class="space-y-3 rounded-lg border border-ink/10 bg-white p-5">
          <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="keep_seniority" value="1" class="mt-1" @checked(old('keep_seniority', true))> <span><b>{{ __('Conserver l’ancienneté') }}</b> {{ __(': les délais de carence de la nouvelle formule partent de la date d’adhésion d’origine (pas de nouvelle carence).') }}</span></label>
          <label class="block text-sm font-medium">{{ __('Motif *') }}<textarea name="note" required minlength="5" maxlength="1000" rows="3" class="{{ $f }}" placeholder="{{ __('Ex. fermeture de la formule ESSENTIEL décidée par l’assemblée générale du …') }}">{{ old('note') }}</textarea></label>
          <button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white">{{ __('Envoyer la demande') }}</button>
        </section>
      @endif
    </form>
  @endif
</x-layouts.tenant>
