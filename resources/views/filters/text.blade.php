@php
    /** @var \Artlosk\LaravelGridView\Filters\Filter $filter */
@endphp

<label for="{{ $inputId }}">{{ $filter->label() }}</label>
<input
    type="text"
    id="{{ $inputId }}"
    name="{{ $filter->inputName() }}"
    value="{{ $value }}"
    @if ($filter->placeholderText()) placeholder="{{ $filter->placeholderText() }}" @endif
    {{ \Artlosk\LaravelGridView\Support\HtmlAttributes::render($filter->inputAttributes()) }}
>
