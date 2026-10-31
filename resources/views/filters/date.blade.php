@php
    /** @var \Artlosk\LaravelGridView\Filters\Filter $filter */
@endphp

<label for="{{ $inputId }}">{{ $filter->label() }}</label>
<input
    type="date"
    id="{{ $inputId }}"
    name="{{ $filter->inputName() }}"
    value="{{ $value }}"
    {{ \Artlosk\LaravelGridView\Support\HtmlAttributes::render($filter->inputAttributes()) }}
>
