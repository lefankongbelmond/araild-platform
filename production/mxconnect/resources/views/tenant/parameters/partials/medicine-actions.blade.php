@php
  $n = (int) ($usage[$m->id] ?? 0);
  $data = [
    'code' => $m->code, 'label' => $m->label, 'presentation' => $m->presentation, 'category' => $m->categoryLabel(),
    'supplier' => $m->supplier, 'price' => $cur->format($m->reference_price_minor), 'currency' => $cur->name . ' (' . $cur->code . ')',
    'usage' => $n ? $n . ' ligne(s) de prestation' : 'Pas encore utilisé dans une prestation',
    'updated' => $m->updated_at?->format('d/m/Y à H:i'), 'description' => $m->description,
  ];
  $btn = 'grid h-9 w-9 place-items-center rounded-lg transition';
@endphp
<div class="flex items-center justify-center gap-0.5">
  <button type="button" class="{{ $btn }} text-brand-600 hover:bg-brand-50" data-med="{{ json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) }}" title="Voir la fiche" aria-label="Voir la fiche de {{ $m->label }}">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12"/><circle cx="12" cy="12" r="3"/></svg>
  </button>
  @can('manage-parameters')
    <a href="{{ route('medicines.index', array_merge(request()->only(['q', 'categorie', 'fournisseur', 'devise', 'page']), ['modifier' => $m->id])) }}#formulaire" class="{{ $btn }} text-[#0B1F3A]/70 hover:bg-paper hover:text-[#0B1F3A]" title="Modifier" aria-label="Modifier {{ $m->label }}">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
    </a>
    <form method="POST" action="{{ route('medicines.destroy', $m) }}" data-confirm="Supprimer « {{ $m->label }} » du catalogue ?{{ $n ? ' Il figure dans ' . $n . ' ligne(s) de prestation : elles restent inchangées.' : '' }}">
      @csrf @method('DELETE')
      @foreach (request()->only(['q', 'categorie', 'fournisseur', 'devise', 'page']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
      <button class="{{ $btn }} text-clay hover:bg-clay/10" title="Supprimer" aria-label="Supprimer {{ $m->label }}">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6"/></svg>
      </button>
    </form>
  @endcan
</div>
