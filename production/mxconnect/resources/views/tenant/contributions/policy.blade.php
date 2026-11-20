<x-layouts.tenant title="{{ __('Règle de suspension') }}">
  @php $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm'; @endphp
  <x-tenant.hero icon="coins" kicker="{{ __('Cotisations') }}" tone="leaf">
    <x-slot:title>{{ __('Règle de suspension des droits') }}</x-slot:title>
    <x-slot:subtitle>{{ __('Quand une échéance de cotisation n’est pas réglée, les droits aux soins sont suspendus. Le délai de grâce laisse à l’adhérent quelques jours après la date d’échéance avant cette suspension.') }}</x-slot:subtitle>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid items-start gap-6 lg:grid-cols-2">
    <section class="space-y-3 rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="font-semibold text-navy">{{ __('Règle actuelle') }}</h2>
      @if ($grace)
        <p>{{ __('Les droits sont suspendus') }} <b>{{ __(':grace jour(s)', ['grace' => $grace]) }}</b> {{ __('après la date d’une échéance restée impayée.') }}</p>
        @php $ex = now()->startOfMonth(); @endphp
        <p class="text-ink/60">{{ __('Exemple : une échéance du :date restée impayée suspend les droits à partir du :date2, sauf si elle est réglée entre-temps.', ['date' => $ex->format('d/m/Y'), 'date2' => $ex->copy()->addDays($grace + 1)->format('d/m/Y')]) }}</p>
      @else
        <p>{{ __('Aucun délai de grâce : les droits sont suspendus') }} <b>{{ __('dès le lendemain') }}</b> {{ __('de la date d’une échéance impayée.') }}</p>
      @endif
      <p class="text-ink/60">{{ __('Pendant le délai de grâce, l’échéance reste') }} <b>{{ __('en retard') }}</b> {{ __(': elle figure dans les arriérés, les relances et les rapports. Seuls les droits aux soins sont maintenus. L’adhérent et le prestataire voient « droits maintenus jusqu’au … ».') }}</p>
    </section>

    @acces('contributions.policy.update')
      <form method="POST" action="{{ route('contributions.policy.update') }}" class="space-y-3 rounded-lg border border-ink/10 bg-white p-5">@csrf
        <h2 class="font-semibold">{{ __('Modifier') }}</h2>
        <label class="block text-sm font-medium">{{ __('Délai de grâce (jours après la date d’échéance)') }}
          <input type="number" name="grace_days" min="0" max="{{ $max }}" value="{{ old('grace_days', $grace) }}" class="{{ $f }}" placeholder="{{ __('0 = aucun délai') }}">
        </label>
        <p class="text-xs text-ink/55">{{ __('Laissez vide ou 0 pour aucun délai. Maximum :max jours. Ce choix appartient à la mutuelle (règlement intérieur) : le logiciel n’en propose aucun par défaut.', ['max' => $max]) }}</p>
        <button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white">{{ __('Enregistrer') }}</button>
      </form>
    @endacces
  </div>
</x-layouts.tenant>
