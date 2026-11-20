<x-layouts.tenant :title="__('Changement :ref', ['ref' => $m->reference])">
  @php
    $S = \App\Models\Tenant\FormulaMigration::STATUSES;
    $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm';
    $fmt = fn ($v) => $v === null ? '—' : $currency->format((int) $v);
    $me = auth()->id();
    $due = $m->effective_date->toDateString() <= now()->toDateString();
  @endphp
  <x-tenant.hero icon="users" kicker="{{ __('Changements de formule') }}" tone="brand">
    <x-slot:title>{{ $m->reference }} : {{ $m->source?->name }} → {{ $m->target?->name }}</x-slot:title>
    <x-slot:subtitle>Date d’effet {{ $m->effective_date->format('d/m/Y') }} · {{ $m->keep_seniority ? __('ancienneté conservée (pas de nouvelle carence)') : __('nouvelle carence à partir de la date d’effet') }} · état : {{ __($S[$m->status] ?? $m->status) }}</x-slot:subtitle>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid items-start gap-6 lg:grid-cols-3">
    <section class="min-w-0 space-y-3 rounded-lg border border-ink/10 bg-white p-5 lg:col-span-2">
      <h2 class="font-semibold text-navy">{{ __('Motif') }}</h2>
      <p class="text-sm">{{ $m->note }}</p>
      <p class="text-xs text-ink/55">Demandé par {{ $people[$m->requested_by] ?? '#' . $m->requested_by }} le {{ $m->created_at->format('d/m/Y à H:i') }}@if ($m->decided_by) · décidé par {{ $people[$m->decided_by] ?? '#' . $m->decided_by }} le {{ $m->decided_at?->format('d/m/Y à H:i') }}@endif @if ($m->applied_at) · appliqué le {{ $m->applied_at->format('d/m/Y à H:i') }}@endif</p>
      @if ($m->decision_note)<p class="text-sm"><b>{{ __('Décision :') }}</b> {{ $m->decision_note }}</p>@endif

      @php $rows = $m->status === 'applied' ? ($m->result['migrated'] ?? []) : ($preview['eligible'] ?? []); $skipped = $m->status === 'applied' ? ($m->result['skipped'] ?? []) : ($preview['skipped'] ?? []); @endphp
      <h2 class="pt-2 font-semibold text-navy">{{ $m->status === 'applied' ? __('Adhérents passés à la nouvelle formule') : __('Adhérents concernés (contrôles refaits à l’application)') }} ({{ count($rows) }})</h2>
      @if ($rows)
        <div class="relative overflow-x-auto">
          <table class="w-full text-sm" style="min-width:520px">
            <thead class="text-left text-ink/50"><tr><th class="py-1 pr-3 font-medium">{{ __('Adhérent') }}</th><th class="py-1 pr-3 text-right font-medium">{{ __('Ancienne cotisation') }}</th><th class="py-1 pr-3 text-right font-medium">{{ __('Nouvelle cotisation') }}</th><th class="py-1 text-right font-medium">{{ __('Échéances retirées') }}</th></tr></thead>
            <tbody class="divide-y divide-ink/5">
              @foreach ($rows as $r)
                <tr><td class="py-2 pr-3">{{ $r['name'] }}<div class="text-xs text-ink/50">{{ $r['code'] }}</div></td><td class="py-2 pr-3 text-right tabular-nums">{{ $fmt($r['old_minor']) }}</td>
                  <td class="py-2 pr-3 text-right tabular-nums">{{ $fmt($r['new_minor']) }}</td><td class="py-2 text-right tabular-nums">{{ $r['removed'] ?? 0 }}</td></tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-sm text-ink/55">{{ __('Aucun adhérent.') }}</p>
      @endif
      @if ($skipped)
        <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          <b>{{ __('Écartés (:count)', ['count' => count($skipped)]) }}</b>
          <ul class="mt-1 space-y-1">@foreach ($skipped as $r)<li>{{ $r['name'] }} ({{ $r['code'] }}) : {{ $r['reason'] }}</li>@endforeach</ul>
        </div>
      @endif
    </section>

    <div class="space-y-4">
      @if ($m->status === 'requested')
        @acces('formula-migrations.approve')
          @if ((int) $m->requested_by !== (int) $me)
            <form method="POST" action="{{ route('formula-migrations.approve', $m) }}" class="space-y-3 rounded-lg border border-ink/10 bg-white p-5">@csrf
              <h2 class="font-semibold">{{ __('Valider') }}</h2>
              <p class="text-xs text-ink/55">{{ $due ? __('La date d’effet est atteinte : le changement sera appliqué tout de suite.') : 'Le changement sera appliqué le ' . $m->effective_date->format('d/m/Y') . ' (tâche de nuit).' }}</p>
              <label class="block text-sm font-medium">{{ __('Commentaire') }}<input name="note" maxlength="500" class="{{ $f }}"></label>
              <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white">{{ __('Valider le changement') }}</button>
            </form>
            <form method="POST" action="{{ route('formula-migrations.reject', $m) }}" class="space-y-3 rounded-lg border border-clay/30 bg-white p-5">@csrf
              <h2 class="font-semibold text-clay">{{ __('Refuser') }}</h2>
              <label class="block text-sm font-medium">{{ __('Motif *') }}<input name="note" required minlength="5" maxlength="500" class="{{ $f }}"></label>
              <button class="w-full rounded-md bg-clay px-4 py-2 text-sm font-semibold text-white">{{ __('Refuser') }}</button>
            </form>
          @else
            <p class="rounded-lg border border-ink/10 bg-white p-5 text-sm text-ink/60">{{ __('Vous avez fait cette demande : une autre personne (administrateur ou contrôleur) doit la valider.') }}</p>
          @endif
        @endacces
      @endif
      @if ($m->status === 'approved' && $due)
        @acces('formula-migrations.apply')
          <form method="POST" action="{{ route('formula-migrations.apply', $m) }}" class="space-y-3 rounded-lg border border-ink/10 bg-white p-5">@csrf
            <h2 class="font-semibold">{{ __('Appliquer') }}</h2>
            <p class="text-xs text-ink/55">{{ __('La date d’effet est atteinte. La tâche de nuit l’appliquera aussi.') }}</p>
            <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white">{{ __('Appliquer maintenant') }}</button>
          </form>
        @endacces
      @endif
      @if (in_array($m->status, ['requested', 'approved'], true))
        @acces('formula-migrations.cancel')
          <form method="POST" action="{{ route('formula-migrations.cancel', $m) }}" onsubmit="return window.confirm('Retirer cette demande ?')" class="rounded-lg border border-ink/10 bg-white p-5">@csrf
            <button class="w-full rounded-md border border-ink/15 px-4 py-2 text-sm font-medium text-ink/70">{{ __('Retirer la demande') }}</button>
          </form>
        @endacces
      @endif
      <a href="{{ route('formula-migrations.index') }}" class="block text-sm font-medium text-pine hover:underline">{{ __('← Tous les changements de formule') }}</a>
    </div>
  </div>
</x-layouts.tenant>
