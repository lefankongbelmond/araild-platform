@props(['paginator'])
@if ($paginator->hasPages())
    <nav class="mt-6 flex items-center justify-between gap-3 text-sm" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="rounded-full border border-ink/10 px-4 py-2 text-ink/30">{{ __('mxconnect.social.common.previous') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="rounded-full border border-ink/15 bg-white px-4 py-2 font-medium text-navy hover:border-navy">{{ __('mxconnect.social.common.previous') }}</a>
        @endif
        <span class="text-ink/50">{{ $paginator->currentPage() }}@if (method_exists($paginator, 'lastPage')) / {{ $paginator->lastPage() }}@endif</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="rounded-full border border-ink/15 bg-white px-4 py-2 font-medium text-navy hover:border-navy">{{ __('mxconnect.social.common.next') }}</a>
        @else
            <span class="rounded-full border border-ink/10 px-4 py-2 text-ink/30">{{ __('mxconnect.social.common.next') }}</span>
        @endif
    </nav>
@endif
