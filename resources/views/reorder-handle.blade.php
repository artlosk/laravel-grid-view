<button
    type="button"
    class="{{ config('grid-view.reorder_handle_class', 'lgv-reorder-handle') }}"
    title="{{ __('grid-view::grid-view.reorder') }}"
    aria-label="{{ __('grid-view::grid-view.reorder') }}"
    data-grid-view-handle
>@if (config('grid-view.reorder_icon'))<i class="{{ config('grid-view.reorder_icon') }}" aria-hidden="true"></i>@else<span aria-hidden="true">⠿</span>@endif</button>
