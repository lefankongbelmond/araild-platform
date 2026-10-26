@php $T = \App\Models\Tenant\ProviderConvention::TYPES; @endphp
<x-layouts.tenant title="Prestataires conventionnés">
  <x-tenant.hero icon="stethoscope" kicker="Paramètres" tone="teal">
    <x-slot:title>Prestataires conventionnés</x-slot:title>
    <x-slot:subtitle>Hôpitaux, centres de santé, pharmacies et laboratoires avec lesquels la mutuelle a signé une convention. Chacun peut recevoir des comptes « Prestataire de soins » pour vérifier les droits des adhérents et déclarer ses prises en charge.</x-slot:subtitle>
    <x-slot:actions>
      @acces('providers.create')<a href="{{ route('providers.create') }}" class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-navy">Ajouter un prestataire</a>@endacces
    </x-slot:actions>
  </x-tenant.hero>

  <div class="mt-6 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">Prestataire</th><th class="px-4 py-2 font-medium">Convention</th>
        <th class="px-4 py-2 font-medium">Comptes actifs</th><th class="px-4 py-2 font-medium">À valider</th><th class="px-4 py-2"></th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($conventions as $c)
          @php $p = $providers[$c->care_provider_id] ?? null; @endphp
          <tr class="{{ $c->active ? '' : 'text-ink/45' }}">
            <td class="px-4 py-3"><b>{{ $p?->name ?? 'Prestataire n° ' . $c->care_provider_id }}</b>
              <div class="text-xs text-ink/50">{{ $T[$p?->type] ?? '' }}@if($p?->locality) · {{ $p->locality }}@endif</div></td>
            <td class="px-4 py-3 text-xs">
              @if ($c->inForce())<span class="rounded-full bg-mist px-2 py-0.5 text-pine">En vigueur</span>
              @elseif (! $c->active)<span class="rounded-full bg-clay/10 px-2 py-0.5 text-clay">Suspendue</span>
              @else<span class="rounded-full bg-amber-50 px-2 py-0.5 text-amber-700">Hors dates</span>@endif
              @if ($c->reference)<div class="mt-1 text-ink/50">N° {{ $c->reference }}</div>@endif
            </td>
            <td class="px-4 py-3">{{ $accounts[$c->care_provider_id] ?? 0 }}</td>
            <td class="px-4 py-3">{{ $pending[$c->care_provider_id] ?? 0 }}</td>
            <td class="px-4 py-3 text-right"><a class="text-pine hover:underline" href="{{ route('providers.show', $c->id) }}">Ouvrir</a></td>
          </tr>
        @empty
          <tr><td colspan="5" class="px-4 py-10 text-center text-ink/45">Aucun prestataire conventionné pour l’instant. Tant que la liste est vide, la saisie des prestations propose tout le registre des prestataires du réseau.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-layouts.tenant>
