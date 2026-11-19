<x-layouts.tenant title="Règle de suspension">
  @php $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm'; @endphp
  <x-tenant.hero icon="coins" kicker="Cotisations" tone="leaf">
    <x-slot:title>Règle de suspension des droits</x-slot:title>
    <x-slot:subtitle>Quand une échéance de cotisation n’est pas réglée, les droits aux soins sont suspendus. Le délai de grâce laisse à l’adhérent quelques jours après la date d’échéance avant cette suspension.</x-slot:subtitle>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid items-start gap-6 lg:grid-cols-2">
    <section class="space-y-3 rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="font-semibold text-navy">Règle actuelle</h2>
      @if ($grace)
        <p>Les droits sont suspendus <b>{{ $grace }} jour(s)</b> après la date d’une échéance restée impayée.</p>
        @php $ex = now()->startOfMonth(); @endphp
        <p class="text-ink/60">Exemple : une échéance du {{ $ex->format('d/m/Y') }} restée impayée suspend les droits à partir du {{ $ex->copy()->addDays($grace + 1)->format('d/m/Y') }}, sauf si elle est réglée entre-temps.</p>
      @else
        <p>Aucun délai de grâce : les droits sont suspendus <b>dès le lendemain</b> de la date d’une échéance impayée.</p>
      @endif
      <p class="text-ink/60">Pendant le délai de grâce, l’échéance reste <b>en retard</b> : elle figure dans les arriérés, les relances et les rapports. Seuls les droits aux soins sont maintenus. L’adhérent et le prestataire voient « droits maintenus jusqu’au … ».</p>
    </section>

    @acces('contributions.policy.update')
      <form method="POST" action="{{ route('contributions.policy.update') }}" class="space-y-3 rounded-lg border border-ink/10 bg-white p-5">@csrf
        <h2 class="font-semibold">Modifier</h2>
        <label class="block text-sm font-medium">Délai de grâce (jours après la date d’échéance)
          <input type="number" name="grace_days" min="0" max="{{ $max }}" value="{{ old('grace_days', $grace) }}" class="{{ $f }}" placeholder="0 = aucun délai">
        </label>
        <p class="text-xs text-ink/55">Laissez vide ou 0 pour aucun délai. Maximum {{ $max }} jours. Ce choix appartient à la mutuelle (règlement intérieur) : le logiciel n’en propose aucun par défaut.</p>
        <button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white">Enregistrer</button>
      </form>
    @endacces
  </div>
</x-layouts.tenant>
