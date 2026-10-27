@php $D = \App\Models\Social\LegalDocument::class; $form = $edit ?? null; @endphp
<x-layouts.network title="Documents légaux">
  <div>
    <h1 class="text-2xl font-semibold tracking-tight">Documents légaux versionnés</h1>
    <p class="mt-1 text-sm text-ink/60">Conditions d’utilisation, politique de confidentialité et règles communautaires présentées dans l’application et sur le site. Une version publiée n’est jamais modifiée : on publie une nouvelle version. Les textes marqués « Modèle » sont à faire valider (juriste, autorité de protection des données) avant publication.</p>
  </div>
  @if (session('success'))<div class="mt-4 rounded-md bg-pine/10 px-4 py-2 text-sm text-pine">{{ session('success') }}</div>@endif
  @if (session('error'))<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-2 text-sm text-clay">{{ session('error') }}</div>@endif
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-2 text-sm text-clay">{{ $errors->first() }}</div>@endif

  @if (! \App\Services\Social\LegalService::ready())
    <p class="mt-6 rounded-lg border border-ink/10 bg-white p-5 text-sm">Les tables des documents légaux n’existent pas encore : appliquez la mise à jour (page d’installation).</p>
  @else
    <div class="mt-6 overflow-x-auto rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-xs uppercase text-ink/60"><tr><th class="px-4 py-3">Document</th><th class="px-4 py-3">Version</th><th class="px-4 py-3">Statut</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($documents->flatten() as $d)
            <tr>
              <td class="px-4 py-3"><b>{{ $d->label() }}</b><div class="text-xs text-ink/45">{{ $d->title }}</div>
                @if ($d->is_template)<div class="text-xs text-clay">Modèle à compléter et à faire valider</div>@endif</td>
              <td class="px-4 py-3">{{ $d->version }}@if ($d->requires_reacceptance)<div class="text-xs text-ink/45">à revalider par tous</div>@endif</td>
              <td class="px-4 py-3">
                @if ($d->status === 'published')<span class="text-pine">Publiée le {{ $d->published_at?->format('d/m/Y') }}</span>
                @elseif ($d->status === 'archived')<span class="text-ink/50">Archivée</span>
                @else Brouillon @endif
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex flex-wrap justify-end gap-2">
                  @if ($d->status === 'draft')
                    <a href="{{ route('network.android.legal', ['modifier' => $d->id]) }}#formulaire" class="rounded border border-ink/15 px-3 py-1.5 text-xs">Modifier</a>
                    <form method="POST" action="{{ route('network.android.legal.publish', $d->id) }}" class="flex flex-wrap items-center justify-end gap-2" onsubmit="return confirm('Publier cette version ? Elle ne pourra plus être modifiée.')">@csrf
                      @if ($d->is_template)<label class="flex items-center gap-1 text-xs text-clay"><input type="checkbox" name="confirm_template" value="1"> publier malgré les parties à compléter</label>@endif
                      <button class="rounded bg-pine px-3 py-1.5 text-xs text-white">Publier</button>
                    </form>
                  @else
                    <a href="{{ $d->url() }}" target="_blank" rel="noopener" class="text-pine hover:underline">Voir ↗</a>
                    <a href="{{ route('network.android.legal', ['type' => $d->type]) }}#formulaire" class="rounded border border-ink/15 px-3 py-1.5 text-xs">Nouvelle version</a>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-4 py-8 text-center text-ink/50">Aucun document.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @php
      $src = $form ?? $base;
      $type = old('type', $src->type ?? 'terms');
    @endphp
    <form id="formulaire" method="POST" action="{{ route('network.android.legal.save') }}" class="mt-8 rounded-lg border border-ink/10 bg-white p-5 text-sm">
      @csrf
      @if ($form)<input type="hidden" name="id" value="{{ $form->id }}">@endif
      <h2 class="text-base font-semibold">{{ $form ? 'Modifier le brouillon' : 'Nouvelle version (brouillon)' }}</h2>
      <p class="mt-1 text-ink/60">Le brouillon n’est visible de personne tant qu’il n’est pas publié. Titres de section : ligne commençant par « ## ». Listes : lignes commençant par « - ».</p>
      <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <label class="block font-medium">Document
          <select name="type" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2">
            @foreach ($D::TYPES as $k => $t)<option value="{{ $k }}" @selected($type === $k)>{{ $t[0] }}</option>@endforeach
          </select></label>
        <label class="block font-medium">Numéro de version<input name="version" required maxlength="20" value="{{ old('version', $form->version ?? '') }}" placeholder="Ex. : 1.1" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2"></label>
        <label class="block font-medium">Titre<input name="title" required maxlength="180" value="{{ old('title', $src->title ?? '') }}" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2"></label>
      </div>
      <label class="mt-3 block font-medium">Résumé (affiché sur l’écran d’acceptation)
        <textarea name="summary" maxlength="500" rows="2" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2">{{ old('summary', $src->summary ?? '') }}</textarea></label>
      <label class="mt-3 block font-medium">Texte complet
        <textarea name="body" required rows="16" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2 font-mono text-xs">{{ old('body', $src->body ?? '') }}</textarea></label>
      <label class="mt-3 flex items-start gap-2"><input type="checkbox" name="requires_reacceptance" value="1" @checked(old('requires_reacceptance', $form->requires_reacceptance ?? false))>
        <span>Changement important : <b>tous les comptes</b> devront valider cette version avant leur prochaine action (sinon, elle ne s’impose qu’aux nouveaux comptes).</span></label>
      <div class="mt-4 flex flex-wrap gap-2">
        <button class="rounded-md bg-pine px-5 py-2.5 text-sm font-medium text-white hover:bg-navy">Enregistrer le brouillon</button>
        @if ($form || $base)<a href="{{ route('network.android.legal') }}" class="rounded-md border border-ink/15 px-5 py-2.5 text-sm">Annuler</a>@endif
      </div>
    </form>
  @endif
</x-layouts.network>
