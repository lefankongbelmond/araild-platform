@php
  $T = \App\Http\Controllers\Tenant\EngagementController::TABS;
  $R = \App\Models\Social\SocialRequest::class;
  $O = \App\Models\Social\Opportunity::class;
@endphp
<x-layouts.tenant title="Projets et opportunités">
  <x-tenant.hero icon="clipboard" kicker="Réseau social" tone="leaf">
    <x-slot:title>Projets, emplois et opportunités</x-slot:title>
    <x-slot:subtitle>Présentez vos projets et leurs résultats, publiez vos offres (emploi, stage, bénévolat, appel à partenaires) et traitez les demandes reçues depuis le réseau.</x-slot:subtitle>
    <x-slot:actions>
      <a href="{{ route('engagement.projects.create') }}" class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-navy">Nouveau projet</a>
      <a href="{{ route('engagement.opportunities.create') }}" class="rounded-md border border-ink/10 bg-white px-4 py-2 text-sm font-medium text-ink/70">Nouvelle offre</a>
    </x-slot:actions>
  </x-tenant.hero>
  @if (session('success'))<div class="mt-4 rounded-md border border-pine/30 bg-mist px-4 py-3 text-sm text-pine">{{ session('success') }}</div>@endif

  <div class="mt-6 flex flex-wrap gap-2 text-sm">
    @foreach ($T as $k => $v)
      <a href="{{ route('engagement.index', ['onglet' => $k === 'demandes' ? null : $k]) }}" class="rounded-md px-3 py-1.5 {{ $tab === $k ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ $v }}</a>
    @endforeach
  </div>

  @if ($tab === 'demandes')
    <div class="mt-4 flex flex-wrap gap-2 text-xs">
      <a href="{{ route('engagement.index') }}" class="rounded-md px-3 py-1.5 {{ ! $status ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">À traiter et acceptées</a>
      @foreach ($R::STATUSES as $k => $l)
        <a href="{{ route('engagement.index', ['statut' => $k]) }}" class="rounded-md px-3 py-1.5 {{ $status === $k ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ $l }}</a>
      @endforeach
    </div>
    <div class="mt-4 overflow-x-auto rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr><th class="px-4 py-2 font-medium">Référence</th><th class="px-4 py-2 font-medium">Demande</th><th class="px-4 py-2 font-medium">Sujet</th><th class="px-4 py-2 font-medium">Reçue le</th><th class="px-4 py-2 font-medium">Statut</th><th></th></tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($items as $r)
            <tr>
              <td class="px-4 py-3 font-mono text-xs">{{ $r->reference }}</td>
              <td class="px-4 py-3"><b>{{ $r->contact_name }}</b><div class="text-xs text-ink/50">{{ $r->kindLabel() }}@if($r->subtypeLabel()) · {{ $r->subtypeLabel() }}@endif</div></td>
              <td class="px-4 py-3">{{ \Illuminate\Support\Str::limit($r->subject(), 60) }}</td>
              <td class="px-4 py-3 text-ink/60">{{ $r->created_at->format('d/m/Y H:i') }}</td>
              <td class="px-4 py-3"><span class="rounded-full bg-mist px-2 py-0.5 text-xs text-pine">{{ $r->statusLabel() }}</span></td>
              <td class="px-4 py-3 text-right"><a class="text-pine hover:underline" href="{{ route('engagement.requests.show', $r->reference) }}">Ouvrir</a></td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-ink/45">Aucune demande dans cette catégorie.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @unless ($canApplications)<p class="mt-2 text-xs text-ink/45">Les candidatures aux offres d’emploi (CV, données personnelles) sont visibles par l’administrateur de la mutuelle uniquement.</p>@endunless
  @elseif ($tab === 'projets')
    <div class="mt-4 overflow-x-auto rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr><th class="px-4 py-2 font-medium">Projet</th><th class="px-4 py-2 font-medium">État</th><th class="px-4 py-2 font-medium">Résultats</th><th class="px-4 py-2 font-medium">Visibilité</th><th></th></tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($items as $p)
            <tr>
              <td class="px-4 py-3"><b>{{ $p->title }}</b><div class="text-xs text-ink/50">{{ \Illuminate\Support\Str::limit($p->summary, 90) }}</div></td>
              <td class="px-4 py-3">{{ $p->phaseLabel() }}</td>
              <td class="px-4 py-3">{{ $p->indicators_count }}</td>
              <td class="px-4 py-3">{{ \App\Models\Social\Project::STATUSES[$p->status] ?? $p->status }}</td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                @if ($p->status === 'published')<a class="text-ink/60 hover:underline" href="{{ $publicBase }}/projets/{{ $p->slug }}" target="_blank" rel="noopener">Voir</a> · @endif
                <a class="text-pine hover:underline" href="{{ route('engagement.projects.edit', $p->id) }}">Modifier</a></td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-4 py-10 text-center text-ink/45">Aucun projet. Créez le premier avec « Nouveau projet ».</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  @else
    <div class="mt-4 overflow-x-auto rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr><th class="px-4 py-2 font-medium">Offre</th><th class="px-4 py-2 font-medium">Date limite</th><th class="px-4 py-2 font-medium">Demandes</th><th class="px-4 py-2 font-medium">Statut</th><th></th></tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($items as $o)
            <tr>
              <td class="px-4 py-3"><b>{{ $o->title }}</b><div class="text-xs text-ink/50">{{ $o->categoryLabel() }}@if($o->status !== 'published') · {{ $o->status === 'draft' ? 'Brouillon' : 'Masquée par la modération' }}@endif</div></td>
              <td class="px-4 py-3 text-ink/60">{{ $o->deadline_on?->format('d/m/Y') ?? '—' }}</td>
              <td class="px-4 py-3">{{ $o->requests_count }}</td>
              <td class="px-4 py-3">
                <form method="POST" action="{{ route('engagement.opportunities.availability', $o->id) }}" class="flex items-center gap-2">@csrf
                  <select name="availability" class="rounded-md border border-ink/15 bg-white px-2 py-1 text-xs" aria-label="Statut de l’offre">@foreach ($O::AVAILABILITIES as $k => $v)<option value="{{ $k }}" @selected($o->availability === $k)>{{ $v }}</option>@endforeach</select>
                  <button class="text-xs text-pine hover:underline">OK</button></form></td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                @if ($o->status === 'published')<a class="text-ink/60 hover:underline" href="{{ $publicBase }}/opportunites/{{ $o->slug }}" target="_blank" rel="noopener">Voir</a> · @endif
                <a class="text-pine hover:underline" href="{{ route('engagement.opportunities.edit', $o->id) }}">Modifier</a></td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-4 py-10 text-center text-ink/45">Aucune offre. Publiez la première avec « Nouvelle offre ».</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  @endif
  <div class="mt-4">{{ $items->links() }}</div>
  <p class="mt-4 text-xs text-ink/45">Aucun paiement ne transite par MX-CONNECT : un soutien financier se convient directement avec le partenaire. Les résultats publiés sont « déclarés » par la mutuelle jusqu’à leur vérification par l’administration du réseau.</p>
</x-layouts.tenant>
