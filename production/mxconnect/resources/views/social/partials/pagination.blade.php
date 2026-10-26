@if ($paginator->hasPages())
  <nav class="mx-row" style="justify-content:center;gap:6px" aria-label="Pagination">
    @if ($paginator->onFirstPage())<span class="mx-btn mx-btn--ghost mx-btn--sm" aria-disabled="true" style="opacity:.5">Précédent</span>
    @else<a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Précédent</a>@endif
    <span class="mx-small mx-muted">Page {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
    @if ($paginator->hasMorePages())<a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Suivant</a>
    @else<span class="mx-btn mx-btn--ghost mx-btn--sm" aria-disabled="true" style="opacity:.5">Suivant</span>@endif
  </nav>
@endif
