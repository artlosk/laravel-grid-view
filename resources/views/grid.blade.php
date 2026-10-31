@php
    /** @var \Artlosk\LaravelGridView\GridView $grid */
    use Artlosk\LaravelGridView\Support\HtmlAttributes;

    $columns = $grid->columns();
    $records = $grid->records();
    $reorderable = $grid->isReorderable();
@endphp

<div
    id="{{ $grid->id() }}"
    class="{{ config('grid-view.grid_class') }}"
    data-grid-view-id="{{ $grid->id() }}"
    @if ($grid->isAjax())
        data-grid-view-ajax
        data-grid-view-header="{{ config('grid-view.partial_header', 'X-Grid-View') }}"
        data-grid-view-sort-parameter="{{ $grid->dataProvider()->sort()->sortParameter() }}"
        data-grid-view-direction-parameter="{{ $grid->dataProvider()->sort()->directionParameter() }}"
        tabindex="-1"
    @endif
    @if ($reorderable)
        data-grid-view-reorder-url="{{ $grid->reorderUrl() }}"
        data-grid-view-reorder-method="{{ $grid->reorderMethod() }}"
    @endif
>
    @if ($grid->filters() !== [])
        @include('grid-view::filters', ['grid' => $grid])
    @endif

    <div class="{{ config('grid-view.table_container_class') }}">
        @if ($grid->caption() !== null)
            <div class="{{ config('grid-view.caption_class') }}">{{ $grid->caption() }}</div>
        @endif
        <div class="{{ config('grid-view.table_wrapper_class') }}">
            <table class="{{ config('grid-view.table_class') }}">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            @php($direction = $grid->sortDirection($column))
                            <th
                                scope="col"
                                {{ HtmlAttributes::render($column->headerAttributes()) }}
                                @if ($direction) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif
                            >
                                @if ($column->isSortable())
                                    <a href="{{ $grid->sortUrl($column) }}" class="lgv-sort" data-grid-view-link>
                                        <span>{{ $column->label() }}</span>
                                        @if ($direction)
                                            <span class="lgv-sort-indicator" aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </a>
                                @else
                                    {{ $column->label() }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                {{-- a row is one line: indentation of every cell adds up on big pages --}}
                @forelse ($records as $index => $model)
                    @php($group = $grid->expandedGroup($model))
                    <tbody{{ HtmlAttributes::render([
                        'class' => implode(' ', array_filter([$grid->rowClass($model), $group->class])) ?: null,
                        'data-grid-view-key' => $reorderable ? $grid->recordKey($model) : null,
                    ]) }}>
                        @foreach ($group->rows as $rowIndex => $row)
                            <tr{{ HtmlAttributes::render(['class' => $row->class]) }}>@foreach ($grid->rowColumns($group, $rowIndex) as $column)<td{{ HtmlAttributes::render($grid->cellAttributes($column, $model, $row, $group)) }}>{{ $column->cell($model, $row, $index) }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td class="lgv-empty" colspan="{{ max(1, count($columns)) }}">{{ $grid->emptyText() }}</td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>

        @if ($records->hasPages())
            <div class="{{ config('grid-view.pagination_wrapper_class') }}" data-grid-view-pagination>
                {{ $records->onEachSide(1)->links(config('grid-view.pagination_view', 'grid-view::pagination')) }}
            </div>
        @endif
    </div>
</div>
