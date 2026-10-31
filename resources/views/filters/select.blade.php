@php
    /** @var \Artlosk\LaravelGridView\Filters\Filter $filter */
    use Artlosk\LaravelGridView\Support\HtmlAttributes;

    $selected = (array) $value;
@endphp

<label for="{{ $inputId }}">{{ $filter->label() }}</label>
<select
    id="{{ $inputId }}"
    name="{{ $filter->inputName() }}"
    @if ($filter->isMultiple()) multiple @endif
    {{ HtmlAttributes::render($filter->inputAttributes()) }}
>
    @unless ($filter->isMultiple())
        <option value="">{{ $filter->emptyLabelText() }}</option>
    @endunless
    @foreach ($filter->items() as $item)
        @isset($item['items'])
            <optgroup label="{{ $item['group'] }}">
                @foreach ($item['items'] as $option)
                    <option
                        value="{{ $option['value'] }}"
                        @selected(in_array($option['value'], $selected, true))
                        {{ HtmlAttributes::render($option['attributes']) }}
                    >{{ $option['label'] }}</option>
                @endforeach
            </optgroup>
        @else
            <option
                value="{{ $item['value'] }}"
                @selected(in_array($item['value'], $selected, true))
                {{ HtmlAttributes::render($item['attributes']) }}
            >{{ $item['label'] }}</option>
        @endisset
    @endforeach
</select>
