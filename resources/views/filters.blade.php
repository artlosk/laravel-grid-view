@php
    /** @var \Artlosk\LaravelGridView\GridView $grid */
    $values = $grid->filterValues();
@endphp

<form
    method="GET"
    action="{{ $grid->filterAction() }}"
    class="{{ config('grid-view.filters_class') }}"
    data-grid-view-filter="{{ $grid->id() }}"
>
    @foreach ($grid->sortQuery() as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach

    @foreach ($grid->filters() as $filter)
        @if ($filter->isHidden())
            @foreach ((array) $values->get($filter->name) as $hiddenValue)
                <input type="hidden" name="{{ $filter->inputName() }}" value="{{ $hiddenValue }}">
            @endforeach
            @continue
        @endif
        <div class="{{ $filter->fieldClassName() ?? config('grid-view.filter_field_class') }}">
            @include('grid-view::filters.'.$filter->type, [
                'filter' => $filter,
                'value' => $values->get($filter->name),
                'inputId' => $grid->id().'-'.$filter->name,
            ])
        </div>
    @endforeach

    <div class="{{ config('grid-view.filter_actions_class') }}">
        <button type="submit" class="{{ config('grid-view.apply_button_class') }}">{{ __('grid-view::grid-view.apply') }}</button>
        @if ($values->active())
            <a href="{{ $grid->resetUrl() }}" class="{{ config('grid-view.reset_button_class') }}" data-grid-view-link>{{ __('grid-view::grid-view.reset') }}</a>
        @endif
    </div>
</form>
