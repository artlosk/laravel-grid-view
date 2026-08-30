@if ($paginator->hasPages())
    <nav class="lgv-pagination" role="navigation" aria-label="{{ __('grid-view::grid-view.pagination') }}">
        @if ($paginator->onFirstPage())
            <span class="lgv-page is-disabled" aria-disabled="true" aria-label="{{ __('grid-view::grid-view.previous_page') }}">‹</span>
        @else
            <a class="lgv-page" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('grid-view::grid-view.previous_page') }}">‹</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="lgv-page is-disabled" aria-disabled="true">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page === $paginator->currentPage())
                        <span class="lgv-page is-current" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="lgv-page" href="{{ $url }}" aria-label="{{ __('grid-view::grid-view.go_to_page', ['page' => $page]) }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="lgv-page" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('grid-view::grid-view.next_page') }}">›</a>
        @else
            <span class="lgv-page is-disabled" aria-disabled="true" aria-label="{{ __('grid-view::grid-view.next_page') }}">›</span>
        @endif
    </nav>
@endif
