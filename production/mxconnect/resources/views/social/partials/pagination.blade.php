@if ($paginator->hasPages())
  <nav class="mx-row" style="justify-content:center;gap:6px" aria-label="{{ __('Pagination') }}">
    @if ($paginator->onFirstPage())<span class="mx-btn mx-btn--ghost mx-btn--sm" aria-disabled="true" style="opacity:.5">{{ __('Précédent') }}</span>
    @else<a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('Précédent') }}</a>@endif
    <span class="mx-small mx-muted">{{ __('Page :currentpage / :lastpage', ['currentpage' => $paginator->currentPage(), 'lastpage' => $paginator->lastPage()]) }}</span>
    @if ($paginator->hasMorePages())<a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Suivant') }}</a>
    @else<span class="mx-btn mx-btn--ghost mx-btn--sm" aria-disabled="true" style="opacity:.5">{{ __('Suivant') }}</span>@endif
  </nav>
@endif
