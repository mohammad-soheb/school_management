{{--
    Paginator view for ->paginate() / ->links() (overrides Laravel's default "pagination::tailwind").
    Styles: resources/scss/components/_pagination.scss.
    "Showing x–y of z results" + Previous / numbered pages with ellipsis / Next. Phones show Previous / "Page x of y" / Next.
    Keep the filters in the links with ->withQueryString() on the paginator.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="pager">
        <p class="pager-info">
            @if ($paginator->firstItem())
                Showing <strong>{{ number_format($paginator->firstItem()) }}</strong>&ndash;<strong>{{ number_format($paginator->lastItem()) }}</strong>
            @else
                Showing <strong>{{ number_format($paginator->count()) }}</strong>
            @endif
            of <strong>{{ number_format($paginator->total()) }}</strong> results
        </p>

        <ul class="pager-list">
            {{-- Previous --}}
            <li>
                @if ($paginator->onFirstPage())
                    <span class="pager-link is-edge is-disabled" aria-disabled="true">
                        <x-ui.icon name="chevron-left" variant="m" /><span class="sr-only sm:not-sr-only">Previous</span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pager-link is-edge">
                        <x-ui.icon name="chevron-left" variant="m" /><span class="sr-only sm:not-sr-only">Previous</span>
                    </a>
                @endif
            </li>

            {{-- Compact position indicator for phones --}}
            <li class="sm:hidden">
                <span class="pager-ellipsis min-w-0 px-3 text-ink-soft" aria-current="page">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
            </li>

            {{-- Page numbers (Laravel passes the "..." separators as strings) --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="hidden sm:block" aria-hidden="true"><span class="pager-ellipsis">&hellip;</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li class="hidden sm:block">
                            @if ($page == $paginator->currentPage())
                                <span class="pager-link is-active" aria-current="page"><span class="sr-only">Page </span>{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="pager-link"><span class="sr-only">Go to page </span>{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pager-link is-edge">
                        <span class="sr-only sm:not-sr-only">Next</span><x-ui.icon name="chevron-right" variant="m" />
                    </a>
                @else
                    <span class="pager-link is-edge is-disabled" aria-disabled="true">
                        <span class="sr-only sm:not-sr-only">Next</span><x-ui.icon name="chevron-right" variant="m" />
                    </span>
                @endif
            </li>
        </ul>
    </nav>
@endif
