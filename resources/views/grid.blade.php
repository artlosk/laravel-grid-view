@php
    $columns = $grid->visibleColumns();
    $records = $grid->records();
@endphp

<div
    id="{{ $grid->id() }}"
    class="{{ $grid->gridClassName() }}"
    @if ($grid->isAjax())
        tabindex="-1"
        data-grid-view-ajax
        data-grid-view-id="{{ $grid->id() }}"
        data-grid-view-partial-header="{{ $grid->partialHeader() }}"
        data-grid-view-partial-id-header="{{ $grid->partialIdHeader() }}"
    @endif
>
    <div class="{{ $grid->tableWrapperClassName() }}">
        <table class="{{ $grid->tableClassName() }}">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th
                            class="{{ $column->getHeaderClass($grid) }}"
                            scope="col"
                            @if ($grid->sortIndicator($column))
                                aria-sort="{{ $grid->sortIndicator($column) === 'asc' ? 'ascending' : 'descending' }}"
                            @endif
                        >
                            @if ($column->isSortable())
                                <a
                                    href="{{ $grid->sortUrl($column) }}"
                                    class="lgv-sort"
                                >
                                    <span>{{ $column->getLabel($grid) }}</span>
                                    @if ($grid->sortIndicator($column))
                                        <span class="lgv-sort-indicator" aria-hidden="true">
                                            {{ $grid->sortIndicator($column) === 'asc' ? '↑' : '↓' }}
                                        </span>
                                    @endif
                                </a>
                            @else
                                {{ $column->getLabel($grid) }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            @forelse ($records as $record)
                @php($group = $grid->expandedGroup($record))
                <tbody class="{{ implode(' ', array_filter([$grid->recordClass($record), $group->class])) }}">
                    @foreach ($group->rows as $rowIndex => $row)
                        <tr class="{{ $row->class }}">
                            @foreach ($columns as $column)
                                @continue($group->shares($column->key()) && $rowIndex > 0)
                                <td
                                    class="{{ $column->getCellClass($record, $row) }}"
                                    @if ($group->shares($column->key()) && $group->rowCount() > 1)
                                        rowspan="{{ $group->rowCount() }}"
                                    @endif
                                >
                                    {{ $column->getValue($record, $row) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            @empty
                <tbody>
                    <tr>
                        <td class="lgv-empty" colspan="{{ max(1, $columns->count()) }}">
                            {{ $grid->emptyMessageText() }}
                        </td>
                    </tr>
                </tbody>
            @endforelse
        </table>
    </div>

    @if ($records->hasPages())
        <div
            data-grid-view-pagination
            @if ($grid->paginationWrapperClassName())
                class="{{ $grid->paginationWrapperClassName() }}"
            @endif
        >
            {{ $records->onEachSide(1)->links($grid->paginationViewName()) }}
        </div>
    @endif
</div>
