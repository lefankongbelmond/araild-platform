<x-layouts.tenant title="{{ __('Changements de formule') }}">
  @php $S = \App\Models\Tenant\FormulaMigration::STATUSES; @endphp
  <x-tenant.hero icon="users" kicker="Formules · contrôle « 4 yeux »" tone="brand">
    <x-slot:title>{{ __('Changements de formule') }}</x-slot:title>
    <x-slot:subtitle>{{ __('Faire passer un groupe d’adhérents d’une formule à une autre à une date d’effet. Une autre personne valide ; le changement s’applique à la date prévue. Les soins antérieurs restent pris en charge selon l’ancienne formule.') }}</x-slot:subtitle>
    @acces('formula-migrations.create')
      <x-slot:actions><a href="{{ route('formula-migrations.create') }}" class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white">{{ __('Nouveau changement') }}</a></x-slot:actions>
    @endacces
  </x-tenant.hero>

  <div class="mt-6 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm" style="min-width:640px">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('Demande') }}</th><th class="px-4 py-2 font-medium">{{ __('De → vers') }}</th><th class="px-4 py-2 font-medium">{{ __('Date d’effet') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('Adhérents') }}</th><th class="px-4 py-2 font-medium">{{ __('État') }}</th><th></th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($migrations as $m)
          <tr class="align-top">
            <td class="px-4 py-3 font-mono">{{ $m->reference }}</td>
            <td class="px-4 py-3">{{ $m->source?->name }} → <b>{{ $m->target?->name }}</b></td>
            <td class="px-4 py-3">{{ $m->effective_date->format('d/m/Y') }}</td>
            <td class="px-4 py-3 tabular-nums">@if ($m->status === 'applied'){{ count($m->result['migrated'] ?? []) }} changé(s) · {{ count($m->result['skipped'] ?? []) }} écarté(s)@else{{ count($m->member_ids ?? []) }} choisi(s)@endif</td>
            <td class="px-4 py-3 text-xs"><b class="{{ in_array($m->status, ['approved', 'applied'], true) ? 'text-pine' : (in_array($m->status, ['rejected', 'cancelled'], true) ? 'text-clay' : 'text-ink/70') }}">{{ __($S[$m->status] ?? $m->status) }}</b></td>
            <td class="px-4 py-3 text-right"><a href="{{ route('formula-migrations.show', $m) }}" class="font-semibold text-pine hover:underline">{{ __('Ouvrir') }}</a></td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-4 py-10 text-center text-ink/45">{{ __('Aucun changement de formule pour l’instant.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $migrations->links() }}</div>
</x-layouts.tenant>
