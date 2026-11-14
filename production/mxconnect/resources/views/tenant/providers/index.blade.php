@php $T = \App\Models\Tenant\ProviderConvention::TYPES; @endphp
<x-layouts.tenant title="Prestataires conventionnés">
  <x-tenant.hero icon="stethoscope" kicker="Paramètres" tone="teal">
    <x-slot:title>Prestataires conventionnés</x-slot:title>
    <x-slot:subtitle>Hôpitaux, centres de santé, pharmacies et laboratoires avec lesquels la mutuelle a signé une convention. Chacun peut recevoir des comptes « Prestataire de soins » pour vérifier les droits des adhérents et déclarer ses prises en charge.</x-slot:subtitle>
    <x-slot:actions>
      @acces('providers.create')<a href="{{ route('providers.create') }}" class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-navy">Ajouter un prestataire</a>@endacces
    </x-slot:actions>
  </x-tenant.hero>

  @if (session('temp_password'))@include('tenant.providers._temp-password')@endif
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  @if ($requests->isNotEmpty())
    <section class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm">
      <h2 class="font-semibold">Demandes d’affiliation reçues ({{ $requests->count() }})</h2>
      <p class="mt-1 text-ink/60">Envoyées par des établissements depuis l’espace prestataire MX-CONNECT. Vérifiez l’établissement (agrément, existence) avant d’accepter : l’acceptation crée la convention.</p>
      <div class="mt-4 space-y-4">
        @foreach ($requests as $r)
          @php $rp = $r->provider; @endphp
          <article class="rounded-lg border border-ink/10 bg-white p-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div><b>{{ $rp?->name ?? 'Établissement n° ' . $r->care_provider_id }}</b>
                <div class="text-xs text-ink/55">{{ $T[$rp?->type] ?? '' }}@if($rp?->locality) · {{ $rp->locality }}@endif @if($rp?->approval_number) · Agrément {{ $rp->approval_number }}@endif @if($rp?->phone) · {{ $rp->phone }}@endif @if($rp?->email) · {{ $rp->email }}@endif</div>
                @if ($rp?->isPending())<div class="mt-1 text-xs text-amber-700">Établissement inscrit en ligne, pas encore reconnu par une mutuelle.</div>@endif
              </div>
              <span class="text-xs text-ink/50">Reçue le {{ $r->created_at?->format('d/m/Y') }}</span>
            </div>
            @if ($r->message)<p class="mt-2 whitespace-pre-line rounded-md bg-mist px-3 py-2">{{ $r->message }}</p>@endif
            @acces('providers.affiliations.approve')
            <details class="mt-3">
              <summary class="cursor-pointer font-medium text-pine">Accepter et créer la convention</summary>
              <form method="POST" action="{{ route('providers.affiliations.approve', $r->id) }}" class="mt-3 space-y-4">@csrf
                @include('tenant.providers._convention-fields', ['convention' => new \App\Models\Tenant\ProviderConvention(), 'f' => 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm focus:border-pine focus:outline-none'])
                <button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-navy">Accepter la demande</button>
              </form>
            </details>
            <form method="POST" action="{{ route('providers.affiliations.reject', $r->id) }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf
              <label class="block flex-1 text-sm font-medium" style="min-width:14rem">Motif du refus <span class="font-normal text-ink/50">(communiqué à l’établissement)</span>
                <input name="note" maxlength="500" class="mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm"></label>
              <button class="rounded-md px-4 py-2 text-sm font-medium text-clay hover:bg-clay/10">Refuser</button>
            </form>
            @endacces
          </article>
        @endforeach
      </div>
    </section>
  @endif

  <div class="mt-6 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">Prestataire</th><th class="px-4 py-2 font-medium">Convention</th>
        <th class="px-4 py-2 font-medium">Comptes actifs</th><th class="px-4 py-2 font-medium">Espace prestataire</th><th class="px-4 py-2 font-medium">À valider</th><th class="px-4 py-2"></th>
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
            <td class="px-4 py-3">{{ ($spaceStaff[$c->care_provider_id] ?? 0) ? $spaceStaff[$c->care_provider_id] . ' personne(s)' : '—' }}</td>
            <td class="px-4 py-3">{{ $pending[$c->care_provider_id] ?? 0 }}</td>
            <td class="px-4 py-3 text-right"><a class="text-pine hover:underline" href="{{ route('providers.show', $c->id) }}">Ouvrir</a></td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-4 py-10 text-center text-ink/45">Aucun prestataire conventionné pour l’instant. Tant que la liste est vide, la saisie des prestations propose tout le registre des prestataires du réseau.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-layouts.tenant>
