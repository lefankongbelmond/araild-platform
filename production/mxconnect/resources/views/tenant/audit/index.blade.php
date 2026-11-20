<x-layouts.tenant :title="__('mxconnect.audit.title')">
  <x-tenant.hero icon="history" kicker="Conformité et qualité" tone="violet">
    <x-slot:title>{{ __('mxconnect.audit.title') }}</x-slot:title>
    <x-slot:subtitle>{{ __('mxconnect.audit.sub') }}</x-slot:subtitle>
  </x-tenant.hero>

  <form method="GET" class="mt-4">
    <select name="type" onchange="this.form.submit()" class="rounded-md border-ink/20 bg-white px-3 py-2 text-sm">
      <option value="">{{ __('mxconnect.audit.all_types') }}</option>
      @foreach ($types as $t)<option value="{{ $t }}" @selected($type===$t)>{{ $t }}</option>@endforeach
    </select>
  </form>

  <div class="mt-4 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.audit.when') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.audit.event') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.audit.object') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.audit.user') }}</th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($audits as $a)
          <tr>
            <td class="px-4 py-2 text-ink/60">{{ \Illuminate\Support\Carbon::parse($a->created_at)->format('d/m/Y H:i') }}</td>
            <td class="px-4 py-2">
              <span class="rounded-full px-2 py-0.5 text-xs {{ ['created'=>'bg-sage/15 text-sage','updated'=>'bg-mist text-ink','deleted'=>'bg-clay/15 text-clay'][$a->event] ?? 'bg-mist' }}">{{ $a->event }}</span>
            </td>
            <td class="px-4 py-2">{{ class_basename($a->auditable_type) }} #{{ $a->auditable_id }}</td>
            <td class="px-4 py-2 text-ink/50">{{ $a->user_id ? '#'.$a->user_id : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="4" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.audit.empty') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $audits->links() }}</div>

  @isset($access)
    <section class="mt-8">
      <h2 class="font-semibold text-navy">Accès aux données de santé et opérations sensibles</h2>
      <p class="mt-1 text-sm text-ink/60">Qui a ouvert un dossier au détail médical, et quand (50 derniers). Aucune donnée médicale n’est recopiée ici.</p>
      <div class="relative mt-3 overflow-x-auto rounded-lg border border-ink/10 bg-white">
        <table class="w-full text-sm" style="min-width:520px">
          <thead class="bg-mist text-left text-ink/60"><tr>
            <th class="px-4 py-2 font-medium">Date</th><th class="px-4 py-2 font-medium">Personne</th><th class="px-4 py-2 font-medium">Accès</th><th class="px-4 py-2 font-medium">Adresse IP</th>
          </tr></thead>
          <tbody class="divide-y divide-ink/5">
            @forelse ($access as $a)
              <tr>
                <td class="px-4 py-2 text-ink/60">{{ \Illuminate\Support\Carbon::parse($a->occurred_at)->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-2">{{ $a->who ?? ($a->user_id ? '#' . $a->user_id : 'Administration du réseau') }}</td>
                <td class="px-4 py-2">@if ($a->action === 'test_data_reset')Réinitialisation de la MUTUELLE TEST @elseif ($a->action === 'identity_updated'){{ __('Identité et langue de la mutuelle modifiées') }} @else{{ \App\Support\HealthAccessLog::LABELS[$a->object_type] ?? $a->object_type }}{{ $a->object_id ? ' #' . $a->object_id : '' }}@endif</td>
                <td class="px-4 py-2 text-ink/50">{{ $a->ip ?? '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="px-4 py-8 text-center text-ink/40">Aucun accès enregistré.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  @endisset
</x-layouts.tenant>
