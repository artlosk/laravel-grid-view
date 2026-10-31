<a
    href="{{ $url }}"
    title="{{ $label }}"
    aria-label="{{ $label }}"
    {{ \Artlosk\LaravelGridView\Support\HtmlAttributes::render($attributes) }}
    @if ($confirm) {{ $confirmAttribute }}="{{ $confirm }}" @endif
>@if ($icon)<i class="{{ $icon }}" aria-hidden="true"></i>@else{{ $label }}@endif</a>
