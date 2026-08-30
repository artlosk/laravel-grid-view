@if ($paginator->hasPages())
    <nav class="host-pagination-view" aria-label="Host pagination">
        Page {{ $paginator->currentPage() }}
    </nav>
@endif
