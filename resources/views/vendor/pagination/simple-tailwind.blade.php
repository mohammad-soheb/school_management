{{--
    Paginator view for ->simplePaginate() (overrides Laravel's default "pagination::simple-tailwind"): Previous / Page n / Next.
    A simple paginator does not know the total, so there is no "of z". Also used by ->cursorPaginate(), which has no
    page numbers (the counts and the middle pill are left out for it).
--}}
@php($paged = method_exists($paginator, 'currentPage'))
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="pager">
        <p class="pager-info">
            @if (! $paged)
                Showing <strong>{{ number_format($paginator->count()) }}</strong> results
            @elseif ($paginator->firstItem())
                Showing <strong>{{ number_format($paginator->firstItem()) }}</strong>&ndash;<strong>{{ number_format($paginator->lastItem()) }}</strong>
            @else
                Showing <strong>{{ number_format($paginator->count()) }}</strong> results
            @endif
        </p>

        <ul class="pager-list">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="pager-link is-edge is-disabled" aria-disabled="true">
                        <x-ui.icon name="chevron-left" variant="m" />Previous
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pager-link is-edge">
                        <x-ui.icon name="chevron-left" variant="m" />Previous
                    </a>
                @endif
            </li>
            @if ($paged)
                <li><span class="pager-link is-active" aria-current="page">Page {{ $paginator->currentPage() }}</span></li>
            @endif
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pager-link is-edge">
                        Next<x-ui.icon name="chevron-right" variant="m" />
                    </a>
                @else
                    <span class="pager-link is-edge is-disabled" aria-disabled="true">
                        Next<x-ui.icon name="chevron-right" variant="m" />
                    </span>
                @endif
            </li>
        </ul>
    </nav>
@endif
