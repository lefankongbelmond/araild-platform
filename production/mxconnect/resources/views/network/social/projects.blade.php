@php $central = rtrim(config('app.url'), '/'); $I = \App\Models\Social\ProjectIndicator::class; @endphp
<x-layouts.network title="Projets et opportunités">
  <div>
    <h1 class="text-2xl font-semibold tracking-tight">Projets, résultats et offres</h1>
    <p class="mt-1 text-sm text-ink/60">Les résultats publiés par les pages sont « déclarés ». Marquez-les « vérifiés » seulement après contrôle sur pièces (rapport, registre, visite). Toute modification du chiffre par la page annule la vérification.</p>
  </div>
  @if (session('success'))<div class="mt-4 rounded-md bg-pine/10 px-4 py-2 text-sm text-pine">{{ session('success') }}</div>@endif
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-2 text-sm text-clay">{{ $errors->first() }}</div>@endif
  <div class="mt-6 flex flex-wrap gap-2 text-sm">
    @foreach (\App\Http\Controllers\Network\ProjectAdminController::TABS as $k => $v)
      <a href="{{ route('network.social.projects', ['onglet' => $k]) }}" class="rounded-md px-3 py-1.5 {{ $tab === $k ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ $v }}</a>
    @endforeach
  </div>
  <div class="mt-4 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      @if (in_array($tab, ['resultats', 'verifies'], true))
        <thead class="bg-mist text-left text-xs uppercase text-ink/60"><tr><th class="px-4 py-3">Résultat</th><th class="px-4 py-3">Projet · page</th><th class="px-4 py-3">Source déclarée</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($items as $i)
            <tr>
              <td class="px-4 py-3"><b>{{ $I::fmt($i->value) }} {{ $i->unit }}</b> — {{ $i->label }}@if($i->target)<div class="text-xs text-ink/45">Objectif : {{ $I::fmt($i->target) }}</div>@endif @if($i->period)<div class="text-xs text-ink/45">{{ $i->period }}</div>@endif</td>
              <td class="px-4 py-3"><a class="text-pine hover:underline" href="{{ $central }}/projets/{{ $i->project?->slug }}" target="_blank" rel="noopener">{{ $i->project?->title }} ↗</a><div class="text-xs text-ink/45">{{ $i->project?->mutual?->name }}</div></td>
              <td class="px-4 py-3 text-ink/70">{{ $i->source_note ?: '—' }}@if($i->verified_at)<div class="text-xs text-pine">Vérifié le {{ $i->verified_at->format('d/m/Y') }} : {{ $i->verification_note }}</div>@endif</td>
              <td class="px-4 py-3 text-right">
                <form method="POST" action="{{ route('network.social.indicators.verify', $i->id) }}" class="flex flex-wrap justify-end gap-2">@csrf
                  @if ($i->verified_at)
                    <input type="hidden" name="action" value="annuler"><button class="rounded border border-ink/15 px-3 py-1.5 text-xs">Retirer la vérification</button>
                  @else
                    <input type="hidden" name="action" value="verifier">
                    <input name="note" maxlength="255" required placeholder="Base de la vérification" class="rounded-md border border-ink/15 bg-white px-2 py-1 text-xs" aria-label="Base de la vérification">
                    <button class="rounded bg-pine px-3 py-1.5 text-xs text-white">Marquer vérifié</button>
                  @endif
                </form></td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-4 py-8 text-center text-ink/50">Aucun résultat dans cette liste.</td></tr>
          @endforelse
        </tbody>
      @else
        <thead class="bg-mist text-left text-xs uppercase text-ink/60"><tr><th class="px-4 py-3">{{ $tab === 'projets' ? 'Projet' : 'Offre' }}</th><th class="px-4 py-3">Page</th><th class="px-4 py-3">Statut</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($items as $x)
            <tr>
              <td class="px-4 py-3"><a class="text-pine hover:underline" href="{{ $central }}/{{ $tab === 'projets' ? 'projets' : 'opportunites' }}/{{ $x->slug }}" target="_blank" rel="noopener">{{ $x->title }} ↗</a><div class="text-xs text-ink/45">créé le {{ $x->created_at?->format('d/m/Y') }}</div></td>
              <td class="px-4 py-3">{{ $x->mutual?->name }}</td>
              <td class="px-4 py-3">{{ ['published' => 'Publié', 'draft' => 'Brouillon', 'hidden' => 'Masqué'][$x->status] ?? $x->status }}</td>
              <td class="px-4 py-3 text-right">@if ($x->status !== 'draft')
                <form method="POST" action="{{ $tab === 'projets' ? route('network.social.projects.toggle', $x->id) : route('network.social.opportunities.toggle', $x->id) }}">@csrf<button class="rounded px-3 py-1.5 text-xs {{ $x->status === 'hidden' ? 'border border-ink/15' : 'bg-clay text-white' }}">{{ $x->status === 'hidden' ? 'Réafficher' : 'Masquer' }}</button></form>
              @endif</td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-4 py-8 text-center text-ink/50">Rien à afficher.</td></tr>
          @endforelse
        </tbody>
      @endif
    </table>
  </div>
  <div class="mt-4">{{ $items->links() }}</div>
</x-layouts.network>
